"""FastAPI website for 100% ONNX Runtime YOLOv8n detection and CNN 5-Channel (7-Class Hard Negative) classification."""
from __future__ import annotations

import json
import sys
import numpy as np
from io import BytesIO
from pathlib import Path
from typing import Annotated

import cv2
import onnxruntime as ort
from fastapi import FastAPI, File, HTTPException, Query, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse
from PIL import Image, UnidentifiedImageError

from dotenv import load_dotenv

SRC_DIR = Path(__file__).resolve().parent
if str(SRC_DIR) not in sys.path:
    sys.path.insert(0, str(SRC_DIR))

from inference_cli import (  # noqa: E402
    DEFAULT_CLASS_NAMES,
    _get_yolo_ort_session,
    _infer_onnx_crop,
    adjust_uniform_bg_leaf_probs,
    analyze_uniform_or_screen_leaf,
    crop_leaf_roi_with_padding,
    detect_foliage_contour_fallback,
    format_class_name,
    is_non_leaf_class,
    is_valid_chili_leaf_box,
    preprocess_cnn_5ch,
    run_onnx_yolo,
    select_primary_detection,
    suppress_overlapping_detections,
)

PROJECT_ROOT = Path(__file__).resolve().parents[1]
load_dotenv(PROJECT_ROOT / ".env")
YOLOV8_MODEL_PATH = PROJECT_ROOT / "models/yolov8n_chili.onnx"
YOLOV8_NAMES_PATH = PROJECT_ROOT / "models/yolov8n_chili.names.json"
CNN_MODEL_PATH = PROJECT_ROOT / "models/cnn_5ch_7kelas_hard_negative.onnx"
CNN_NAMES_PATH = PROJECT_ROOT / "models/cnn_5ch_7kelas_hard_negative.names.json"
SCORE_THRESHOLD = 0.25
YOLO_THRESHOLD = 0.25
MODEL_DEFINITIONS = {
    "yolov8n-chili": {
        "name": "Yolov8n (Deteksi Penyakit Daun Cabai)",
        "path": YOLOV8_MODEL_PATH,
        "names_path": YOLOV8_NAMES_PATH,
        "kind": "yolo",
    },
    "cnn-5ch-chili": {
        "name": "CNN (Klasifikasi Penyakit Daun Cabai)",
        "path": CNN_MODEL_PATH,
        "names_path": CNN_NAMES_PATH,
        "kind": "classification",
    },
}
classifier_session: tuple[ort.InferenceSession, str] | None = None

app = FastAPI(title="Chili Leaf Detection Website", version="3.0.0-onnx")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


@app.middleware("http")
async def disable_ui_cache(request, call_next):
    response = await call_next(request)
    if request.url.path == "/":
        response.headers["Cache-Control"] = "no-store"
    return response


def load_yolo_names(model_id: str) -> tuple[Path, list[str]]:
    definition = MODEL_DEFINITIONS[model_id]
    path: Path = definition["path"]
    if not path.is_file():
        raise HTTPException(status_code=503, detail=f"Model YOLO ONNX belum tersedia: {path.name}")
    names_path: Path | None = definition.get("names_path")
    if names_path is not None and names_path.is_file():
        names = [format_class_name(str(item)) for item in json.loads(names_path.read_text(encoding="utf-8"))]
    else:
        names = list(DEFAULT_CLASS_NAMES[:6])
    return path, names


def np_from_image(image: Image.Image):
    return np.asarray(image.convert("RGB"))


def load_classifier() -> tuple[ort.InferenceSession, str, list[str]]:
    global classifier_session
    path = MODEL_DEFINITIONS["cnn-5ch-chili"]["path"]
    if not path.is_file():
        raise HTTPException(status_code=503, detail=f"Model CNN ONNX belum tersedia: {path.name}")
    if classifier_session is None:
        sess = ort.InferenceSession(str(path), providers=["CPUExecutionProvider"])
        classifier_session = (sess, sess.get_inputs()[0].name)
    sess, input_name = classifier_session
    if CNN_NAMES_PATH.is_file():
        class_names = [format_class_name(str(item)) for item in json.loads(CNN_NAMES_PATH.read_text(encoding="utf-8"))]
    else:
        class_names = list(DEFAULT_CLASS_NAMES)
    return sess, input_name, class_names


def suppress_overlapping_detections(
    detections: list[dict[str, object]], iou_threshold: float = 0.45
) -> list[dict[str, object]]:
    """Keep the strongest box when the model reports the same object repeatedly."""
    kept: list[dict[str, object]] = []
    for candidate in sorted(
        detections, key=lambda item: float(item["confidence"]), reverse=True
    ):
        box = candidate["bbox_xyxy"]
        if not isinstance(box, list):
            continue
        x1, y1, x2, y2 = box
        area = max(0.0, x2 - x1) * max(0.0, y2 - y1)
        overlaps = False
        for existing in kept:
            other = existing["bbox_xyxy"]
            ox1, oy1, ox2, oy2 = other
            other_area = max(0.0, ox2 - ox1) * max(0.0, oy2 - oy1)
            intersection = max(0.0, min(x2, ox2) - max(x1, ox1)) * max(
                0.0, min(y2, oy2) - max(y1, oy1)
            )
            union = area + other_area - intersection
            min_area = min(area, other_area)
            same_class = candidate["class_id"] == existing["class_id"]
            if same_class:
                if (union and intersection / union >= iou_threshold) or (min_area > 0 and intersection / min_area >= 0.75):
                    overlaps = True
                    break
            else:
                if (union and intersection / union >= 0.50) or (min_area > 0 and intersection / min_area >= 0.70):
                    overlaps = True
                    break
        if not overlaps:
            kept.append(candidate)
    return kept[:50]


@app.get("/", response_class=HTMLResponse)
def website() -> str:
    return """<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ChiliVision · Smart Crop Health</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17211b;background:#f5f8f3;--green:#177245;--dark:#102a1d;--mint:#e7f4e9;--line:#dce7dc}
*{box-sizing:border-box}body{margin:0;background:linear-gradient(135deg,#f7fbf6 0%,#edf6ed 55%,#dcefe0 100%);min-height:100vh}
.shell{max-width:1240px;margin:auto;padding:28px 22px 52px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:58px}
.brand{display:flex;align-items:center;gap:12px;font-weight:800;letter-spacing:-.03em;color:var(--dark)}.brand-mark{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:var(--green);color:#eaffeb;font-size:22px;box-shadow:0 7px 16px #17724533}
.top-link{color:#4d6251;text-decoration:none;font-size:.92rem}.hero{max-width:810px}.eyebrow{display:inline-flex;align-items:center;gap:7px;color:var(--green);font-size:.76rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.eyebrow:before{content:"";width:20px;height:2px;background:#67a878}
.hero h1{font-size:clamp(2.4rem,5vw,4.7rem);line-height:1.02;letter-spacing:-.065em;margin:15px 0 18px;color:var(--dark)}.hero p{max-width:650px;color:#617265;font-size:1.08rem;line-height:1.7;margin:0}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:34px 0 22px}.stat{background:#ffffffaa;border:1px solid #fff;border-radius:16px;padding:18px 20px;box-shadow:0 8px 24px #204a2710}.stat strong{display:block;color:var(--dark);font-size:1.1rem;margin-bottom:5px}.stat span{color:#708071;font-size:.86rem}
.workspace{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(310px,.65fr);gap:22px;margin-top:22px}.card{background:#fff;border:1px solid var(--line);border-radius:24px;padding:24px;box-shadow:0 18px 45px #204a2712}.card-title{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px}.card h2{color:var(--dark);font-size:1.15rem;letter-spacing:-.02em;margin:0}.card-subtitle{color:#809083;font-size:.82rem;margin:6px 0 0}
.pill{display:inline-flex;align-items:center;gap:6px;color:#257044;background:var(--mint);border-radius:99px;padding:7px 11px;font-size:.76rem;font-weight:700;white-space:nowrap}.pill:before{content:"";width:7px;height:7px;border-radius:50%;background:#48a86a}
.preview{position:relative;background:#11291d;border-radius:18px;width:100%;min-height:360px;height:auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:5px solid #f1f7f1;margin-left:auto;margin-right:auto}.preview:has(#uploadedImage:not([hidden])),.preview:has(#video:not([hidden])){width:fit-content;max-width:100%;min-height:0;height:auto}#video{display:block;width:auto;height:auto;max-width:100%;object-fit:contain}#uploadedImage{display:block;width:auto;height:auto;max-width:100%;object-fit:contain}[hidden]{display:none!important}.overlay{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}.placeholder{color:#b9d1bd;padding:52px;text-align:center;line-height:1.6}.placeholder strong{display:block;color:#ecf8ed;font-size:1.1rem;margin-bottom:8px}
.toolbar{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.toolbar button{margin-top:0}.field-label{display:block;font-weight:700;color:#3d5142;font-size:.8rem;margin-bottom:7px}button,select{width:100%;border:0;border-radius:11px;padding:12px 14px;font-size:.92rem}button{background:var(--green);color:#fff;font-weight:750;cursor:pointer;transition:transform .15s,background .15s}button:hover{background:#0f5a35;transform:translateY(-1px)}button.secondary{background:#edf5ee;color:#25613b}button.secondary:hover{background:#dcecdf}select{background:#f8fbf8;color:#26382b;border:1px solid var(--line);outline:none}
.upload-zone{border:1.5px dashed #aec8b0;border-radius:14px;padding:18px;margin-top:20px;background:#fbfefb}.upload-zone input{width:100%;margin-top:8px;color:#718071;font-size:.84rem}.upload-zone button{margin-top:14px}.upload-zone button+button{margin-top:10px}.status{color:#477057;min-height:22px;margin:16px 0 0;font-size:.85rem}.mode{color:#8a6a22;background:#fff8df;border-radius:9px;padding:10px 12px;font-size:.8rem;margin:10px 0 0}
.result{color:#3e5344;background:#f7fbf7;border:1px solid #e4eee4;border-radius:14px;padding:14px;margin-top:16px;min-height:76px}.detection{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #e3ece3}.detection:last-child{border-bottom:0}.confidence{color:#177245;font-weight:800}.info-list{display:grid;gap:16px;margin-top:20px}.info-item{display:flex;gap:13px;align-items:flex-start;padding:12px 0;border-top:1px solid #edf3ed}.info-icon{flex:0 0 32px;height:32px;display:grid;place-items:center;border-radius:9px;background:var(--mint);color:var(--green);font-size:.76rem;font-weight:800}.info-copy{min-width:0;padding-top:1px}.info-item strong{display:block;color:#2d4433;font-size:.86rem;line-height:1.3;margin:0 0 6px}.info-item .info-description{display:block;color:#819083;font-size:.78rem;line-height:1.55;margin:0}
.footer{color:#7b8b7d;text-align:center;font-size:.78rem;margin-top:27px}@media(max-width:800px){.workspace{grid-template-columns:1fr}.stats{grid-template-columns:1fr}.topbar{margin-bottom:38px}.preview{min-height:290px}.toolbar{grid-template-columns:1fr}}
</style></head>
<body><main class="shell">
<nav class="topbar"><div class="brand"><span class="brand-mark">✦</span><span>ChiliVision</span></div><a class="top-link" href="/docs">API Documentation ↗</a></nav>
<section class="hero"><span class="eyebrow">AI-powered crop monitoring</span><h1>Kenali kesehatan daun cabai lebih cepat.</h1><p>ChiliVision membantu mengidentifikasi penyakit dan kondisi daun cabai dari kamera atau gambar menggunakan computer vision, sehingga keputusan perawatan bisa dibuat lebih tepat.</p></section>
<section class="stats"><div class="stat"><strong>Two AI models</strong><span>YOLOv8n dan CNN 5-Channel</span></div><div class="stat"><strong>Real-time ready</strong><span>Preview kamera langsung</span></div><div class="stat"><strong>API-first</strong><span>Siap diintegrasikan ke IoT</span></div></section>
<section class="workspace">
<div class="card"><div class="card-title"><div><h2>Detection workspace</h2><p class="card-subtitle">Arahkan kamera ke daun atau upload foto untuk memulai analisis.</p></div><span class="pill">System ready</span></div>
<div class="preview"><video id="video" autoplay playsinline hidden></video><img id="uploadedImage" alt="Gambar upload dengan hasil deteksi" hidden><canvas id="overlay" class="overlay" hidden></canvas><div id="placeholder" class="placeholder"><strong>Belum ada gambar untuk dianalisis</strong>Aktifkan kamera atau pilih foto daun cabai dari perangkat Anda.</div></div>
<div class="toolbar"><div><label class="field-label" for="cameraSelect">Sumber kamera</label><select id="cameraSelect"><option>Memuat kamera...</option></select></div><div><label class="field-label" for="model">Model analisis</label><select id="model"></select></div></div>
<div class="toolbar"><button id="camera">Aktifkan kamera</button><button id="predict" class="secondary" disabled>Mulai deteksi langsung</button></div>
<div class="upload-zone"><label class="field-label" for="upload">Atau gunakan gambar dari perangkat</label><input id="upload" type="file" accept="image/jpeg,image/png,image/webp"><button id="uploadPredict" class="secondary">Upload & deteksi gambar</button><button id="showCamera" class="secondary" hidden>Kembali ke kamera</button></div>
<p class="status" id="status">Memuat daftar model...</p></div>
<aside class="card"><div class="card-title"><div><h2>Insight hasil analisis</h2><p class="card-subtitle">Ringkasan prediksi dari model yang dipilih.</p></div></div><p class="mode" id="mode">Mode: memuat...</p><div class="result" id="result">Hasil deteksi akan muncul di sini setelah gambar dianalisis.</div><div class="result" id="uploadResult">Hasil upload akan ditampilkan di panel ini.</div><div class="info-list"><div class="info-item"><span class="info-icon">01</span><div class="info-copy"><strong>Bounding box</strong><span class="info-description">Model deteksi menandai area objek pada gambar.</span></div></div><div class="info-item"><span class="info-icon">02</span><div class="info-copy"><strong>Confidence score</strong><span class="info-description">Semakin tinggi nilainya, semakin yakin model terhadap prediksi.</span></div></div><div class="info-item"><span class="info-icon">03</span><div class="info-copy"><strong>Pipeline terhubung</strong><span class="info-description">Hasil dapat diakses melalui endpoint POST /predict.</span></div></div></div></aside>
</section><p class="footer">ChiliVision · Computer vision untuk pertanian yang lebih cerdas</p></main>
<script>
const video=document.getElementById("video"), uploadedImage=document.getElementById("uploadedImage"), overlay=document.getElementById("overlay"), overlayContext=overlay.getContext("2d"), placeholder=document.getElementById("placeholder"), cameraSelect=document.getElementById("cameraSelect"), camera=document.getElementById("camera"), predict=document.getElementById("predict"), upload=document.getElementById("upload"), uploadPredict=document.getElementById("uploadPredict"), showCamera=document.getElementById("showCamera"), uploadResult=document.getElementById("uploadResult"), model=document.getElementById("model"), mode=document.getElementById("mode"), status=document.getElementById("status"), result=document.getElementById("result"); let stream, detectionTimer=null, requestInFlight=false, uploadObjectUrl=null;
function isLiveModel(){return model.value==="yolov8n-chili"}
function getThreshold(){return isLiveModel()?"0.45":"0.25"}
function updateMode(){if(isLiveModel()){mode.textContent="Mode: live detection setiap 0,5 detik";predict.textContent="Mulai deteksi langsung"}else{mode.textContent="Mode foto sekali: klasifikasi penyakit tanpa bounding box";predict.textContent="Ambil foto & klasifikasi"}if(detectionTimer)stopDetection();clearBoxes()}
fetch("/models").then(r=>r.json()).then(items=>{items.forEach(x=>{const o=document.createElement("option");o.value=x.id;o.textContent=x.name+(x.available?"":" — belum tersedia");o.disabled=!x.available;model.appendChild(o)});const active=items.find(x=>x.available);if(active)model.value=active.id;updateMode();status.textContent=active?"Model siap digunakan.":"Model belum ditemukan.";});
model.addEventListener("change",updateMode);
async function loadCameras(){try{await fetch("/cameras");const devices=await navigator.mediaDevices.enumerateDevices();const cameras=devices.filter(x=>x.kind==="videoinput");cameraSelect.innerHTML="";cameras.forEach((x,index)=>{const o=document.createElement("option");o.value=x.deviceId;o.textContent=x.label||`Kamera ${index}`;cameraSelect.appendChild(o)});if(!cameras.length)cameraSelect.innerHTML="<option value=''>Kamera tidak terdeteksi</option>";}catch(e){cameraSelect.innerHTML="<option value=''>Izinkan akses kamera terlebih dahulu</option>"}}
loadCameras();
async function startCamera(){resetPreview();const deviceId=cameraSelect.value;stream=await navigator.mediaDevices.getUserMedia({video:deviceId?{deviceId:{exact:deviceId}}:true});video.srcObject=stream;video.hidden=false;uploadedImage.hidden=true;overlay.hidden=false;placeholder.hidden=true;showCamera.hidden=true;predict.disabled=false;camera.textContent="Matikan kamera";camera.onclick=stop}
camera.onclick=async()=>{try{await startCamera();await loadCameras()}catch(e){status.textContent="Kamera tidak dapat diakses. Izinkan akses kamera di browser lalu coba lagi."}};
function stopCameraForUpload(){stopDetection();if(stream){stream.getTracks().forEach(track=>track.stop());stream.getTracks().forEach(track=>track.enabled=false);stream=null}video.pause();video.srcObject=null;video.hidden=true;predict.disabled=true;camera.textContent="Aktifkan kamera";camera.onclick=async()=>{try{await startCamera()}catch(e){status.textContent="Kamera tidak dapat diakses. Izinkan akses kamera di browser lalu coba lagi."}}}
function resetPreview(){stopCameraForUpload();if(uploadObjectUrl){URL.revokeObjectURL(uploadObjectUrl);uploadObjectUrl=null}uploadedImage.removeAttribute("src");uploadedImage.hidden=true;video.hidden=true;overlay.hidden=true;placeholder.hidden=false;showCamera.hidden=true;clearBoxes();upload.value="";result.textContent="Hasil deteksi akan muncul di sini setelah gambar dianalisis.";uploadResult.textContent="Hasil upload akan ditampilkan di panel ini.";status.textContent="Aktifkan kamera atau upload gambar untuk memulai.";camera.textContent="Aktifkan kamera";camera.onclick=async()=>{try{await startCamera()}catch(e){status.textContent="Kamera tidak dapat diakses. Izinkan akses kamera di browser lalu coba lagi."}}}
function stop(){resetPreview()}
function clearBoxes(){overlayContext.clearRect(0,0,overlay.width,overlay.height)}
function drawBoxes(detections,width,height){const overlayRect=overlay.getBoundingClientRect(),target=uploadedImage.hidden?video:uploadedImage,targetRect=target.getBoundingClientRect();overlay.width=Math.round(overlayRect.width);overlay.height=Math.round(overlayRect.height);clearBoxes();const boxAspect=targetRect.width/targetRect.height,imageAspect=width/height;let contentWidth=targetRect.width,contentHeight=targetRect.height,contentX=targetRect.left-overlayRect.left,contentY=targetRect.top-overlayRect.top;if(imageAspect>boxAspect){contentHeight=contentWidth/imageAspect;contentY+=(targetRect.height-contentHeight)/2}else{contentWidth=contentHeight*imageAspect;contentX+=(targetRect.width-contentWidth)/2}const targetRight=contentX+contentWidth,targetBottom=contentY+contentHeight,scaleX=contentWidth/width,scaleY=contentHeight/height;overlayContext.lineWidth=3;overlayContext.font="700 13px Inter, Segoe UI, sans-serif";detections.forEach((x,index)=>{const [x1,y1,x2,y2]=x.bbox_xyxy;const left=Math.max(contentX,Math.min(targetRight,contentX+x1*scaleX)),top=Math.max(contentY,Math.min(targetBottom,contentY+y1*scaleY)),right=Math.max(left,Math.min(targetRight,contentX+x2*scaleX)),bottom=Math.max(top,Math.min(targetBottom,contentY+y2*scaleY));const text=`${index+1}. ${x.class_name} ${(x.confidence*100).toFixed(1)}%`;overlayContext.strokeStyle="#49c778";overlayContext.strokeRect(left,top,right-left,bottom-top);const textWidth=overlayContext.measureText(text).width+18,labelHeight=25,labelX=Math.min(Math.max(contentX,left),Math.max(contentX,targetRight-textWidth)),aboveY=top-labelHeight,labelY=aboveY>=contentY?aboveY:Math.min(bottom,targetBottom-labelHeight);overlayContext.fillStyle="#49c778";overlayContext.fillRect(labelX,labelY,textWidth,labelHeight);overlayContext.fillStyle="#092315";overlayContext.fillText(text,labelX+9,labelY+17)})}
async function detectFrame(){if(requestInFlight||!stream)return;requestInFlight=true;try{if(video.readyState<2||!video.videoWidth){status.textContent="Menunggu kamera siap...";return}const canvas=document.createElement("canvas");canvas.width=video.videoWidth;canvas.height=video.videoHeight;canvas.getContext("2d").drawImage(video,0,0);const blob=await new Promise(r=>canvas.toBlob(r,"image/jpeg",.82));if(!blob)throw new Error("Gambar kamera tidak dapat dibuat.");const data=new FormData();data.append("file",blob,"webcam.jpg");const response=await fetch("/predict?model_id="+encodeURIComponent(model.value)+"&threshold="+getThreshold(),{method:"POST",body:data});const payload=await response.json();if(!response.ok)throw new Error(payload.detail||"Deteksi gagal.");drawBoxes(payload.detections,payload.image_width,payload.image_height);if(!payload.detections.length){result.textContent="Tidak ada deteksi di atas threshold.";status.textContent=isLiveModel()?"Mencari objek...":"Tidak ada objek terdeteksi.";}else{const primary=payload.primary_detection;result.innerHTML=`<div class="detection"><span>Hasil utama: ${primary.class_name}</span><span class="confidence">${(primary.confidence*100).toFixed(1)}%</span></div>`+payload.detections.slice(1).map(x=>`<div class="detection"><span>${x.class_name}</span><span class="confidence">${(x.confidence*100).toFixed(1)}%</span></div>`).join("");status.textContent=`${payload.detections.length} objek terdeteksi.`;}}catch(e){status.textContent=e.message;}finally{requestInFlight=false;}}
async function capturePhotoAndDetect(){if(requestInFlight||!stream)return;requestInFlight=true;try{if(video.readyState<2||!video.videoWidth){status.textContent="Kamera belum siap.";return}const canvas=document.createElement("canvas");canvas.width=video.videoWidth;canvas.height=video.videoHeight;canvas.getContext("2d").drawImage(video,0,0);const blob=await new Promise(r=>canvas.toBlob(r,"image/jpeg",.92));if(!blob)throw new Error("Foto tidak dapat dibuat.");if(uploadObjectUrl)URL.revokeObjectURL(uploadObjectUrl);uploadedImage.src=uploadObjectUrl=URL.createObjectURL(blob);uploadedImage.hidden=false;video.hidden=true;overlay.hidden=false;placeholder.hidden=true;showCamera.hidden=false;stream.getTracks().forEach(t=>t.stop());stream=null;video.pause();video.srcObject=null;predict.disabled=true;camera.textContent="Reset tampilan";camera.onclick=resetPreview;await new Promise(r=>{if(uploadedImage.complete)r();else uploadedImage.onload=r});status.textContent="Foto berhasil diambil. Menganalisis penyakit...";const data=new FormData();data.append("file",blob,"capture.jpg");const response=await fetch("/predict?model_id="+encodeURIComponent(model.value)+"&threshold="+getThreshold(),{method:"POST",body:data,cache:"no-store"});const payload=await response.json();if(!response.ok)throw new Error(payload.detail||"Deteksi gagal.");if(payload.classification){const classification=payload.classification;result.innerHTML=`<div class="detection"><span>${classification.class_name}</span><span class="confidence">${(classification.confidence*100).toFixed(1)}%</span></div>`;uploadResult.textContent="Foto CNN 5-Channel selesai diklasifikasikan.";status.textContent="Foto selesai dianalisis. Klik Reset tampilan untuk menghapus foto."}else{drawBoxes(payload.detections,payload.image_width,payload.image_height);if(!payload.detections.length){result.textContent="Tidak ada penyakit terdeteksi di atas threshold.";status.textContent="Foto sudah dianalisis, tidak ada objek terdeteksi."}else{const primary=payload.primary_detection;result.innerHTML=`<div class="detection"><span>#1 ${primary.class_name}</span><span class="confidence">${(primary.confidence*100).toFixed(1)}%</span></div>`+payload.detections.slice(1).map((x,i)=>`<div class="detection"><span>#${i+2} ${x.class_name}</span><span class="confidence">${(x.confidence*100).toFixed(1)}%</span></div>`).join("");status.textContent=`Foto dianalisis: ${payload.detections.length} objek terdeteksi.`}}}catch(e){status.textContent=e.message}finally{requestInFlight=false}}
function startDetection(){if(detectionTimer)return;predict.textContent="Hentikan deteksi langsung";status.textContent="Mencari objek...";detectFrame();detectionTimer=setInterval(detectFrame,500)}
function stopDetection(){if(detectionTimer){clearInterval(detectionTimer);detectionTimer=null;}predict.textContent=isLiveModel()?"Mulai deteksi langsung":"Ambil foto & klasifikasi";clearBoxes()}
predict.onclick=()=>isLiveModel()?(detectionTimer?stopDetection():startDetection()):capturePhotoAndDetect();
uploadPredict.onclick=async()=>{const file=upload.files[0];if(!file){status.textContent="Pilih gambar terlebih dahulu.";return}stopCameraForUpload();if(uploadObjectUrl)URL.revokeObjectURL(uploadObjectUrl);uploadedImage.src=uploadObjectUrl=URL.createObjectURL(file);uploadedImage.hidden=false;placeholder.hidden=true;overlay.hidden=false;showCamera.hidden=false;status.textContent="Menganalisis gambar upload...";const data=new FormData();data.append("file",file);try{const response=await fetch("/predict?model_id="+encodeURIComponent(model.value)+"&threshold="+getThreshold()+"&request_id="+Date.now(),{method:"POST",body:data,cache:"no-store",headers:{"Cache-Control":"no-cache"}});const payload=await response.json();if(!response.ok)throw new Error(payload.detail||"Deteksi gagal.");if(!uploadedImage.complete)await new Promise(r=>uploadedImage.onload=r);drawBoxes(payload.detections||[],payload.image_width,payload.image_height);if(payload.classification){const classification=payload.classification;uploadResult.innerHTML=`<div class="detection"><span>Hasil klasifikasi: ${classification.class_name}</span><span class="confidence">${(classification.confidence*100).toFixed(1)}%</span></div>`;result.innerHTML=uploadResult.innerHTML;status.textContent="Foto berhasil diklasifikasikan."}else if(!payload.detections.length){uploadResult.textContent="Upload: tidak ada deteksi di atas threshold.";result.textContent=uploadResult.textContent;status.textContent="Tidak ada objek terdeteksi."}else{const primary=payload.primary_detection;uploadResult.innerHTML=`<div class="detection"><span>Hasil upload: ${primary.class_name}</span><span class="confidence">${(primary.confidence*100).toFixed(1)}%</span></div>`+payload.detections.slice(1).map(x=>`<div class="detection"><span>${x.class_name}</span><span class="confidence">${(x.confidence*100).toFixed(1)}%</span></div>`).join("");result.innerHTML=uploadResult.innerHTML;status.textContent=`${payload.detections.length} objek upload terdeteksi.`}}catch(e){status.textContent=e.message}};
upload.addEventListener("change",()=>{const file=upload.files[0];if(file){stopCameraForUpload();if(uploadObjectUrl)URL.revokeObjectURL(uploadObjectUrl);uploadedImage.src=uploadObjectUrl=URL.createObjectURL(file);uploadedImage.hidden=false;placeholder.hidden=true;overlay.hidden=false;showCamera.hidden=false;clearBoxes();status.textContent="Gambar dipilih. Klik Upload & deteksi gambar untuk menganalisis."}});
showCamera.onclick=async()=>{try{await startCamera()}catch(e){status.textContent="Kamera tidak dapat diakses. Izinkan akses kamera di browser lalu coba lagi."}};
</script></body></html>"""


@app.get("/models")
def models() -> list[dict[str, object]]:
    return [
        {"id": key, "name": value["name"], "filename": value["path"].name, "available": value["path"].is_file()}
        for key, value in MODEL_DEFINITIONS.items()
    ]


@app.get("/cameras")
def cameras() -> list[dict[str, object]]:
    detected: list[dict[str, object]] = []
    for index in range(5):
        capture = cv2.VideoCapture(index, cv2.CAP_DSHOW)
        if capture.isOpened():
            detected.append({"index": index, "name": f"Kamera {index}"})
        capture.release()
    return detected


@app.get("/health")
def health() -> dict[str, object]:
    return {
        "status": "ok",
        "models": len(MODEL_DEFINITIONS),
        "available_models": [
            key for key, definition in MODEL_DEFINITIONS.items() if definition["path"].is_file()
        ],
    }


@app.get("/api")
def api_info() -> dict[str, object]:
    return {
        "name": app.title,
        "version": app.version,
        "endpoints": {
            "health": "GET /health",
            "models": "GET /models",
            "predict": "POST /predict?model_id=yolov8n-chili&threshold=0.25",
        },
        "predict_request": {
            "content_type": "multipart/form-data",
            "field": "file",
            "file_type": "image/jpeg, image/png, atau image/webp",
        },
        "predict_response": {
            "count": "jumlah objek terdeteksi",
            "detections": [
                {
                    "class_id": "nomor kelas",
                    "class_name": "label penyakit",
                    "confidence": "nilai 0 sampai 1",
                    "bbox_xyxy": "[x_min, y_min, x_max, y_max]",
                }
            ],
        },
    }


@app.post("/predict")
async def predict(
    file: Annotated[UploadFile, File(description="Gambar dari webcam")],
    model_id: Annotated[str, Query(description="ID model dari GET /models")] = "yolov8n-chili",
    threshold: Annotated[float, Query(ge=0.0, le=1.0)] = SCORE_THRESHOLD,
) -> dict[str, object]:
    definition = MODEL_DEFINITIONS.get(model_id)
    if definition is None:
        raise HTTPException(status_code=404, detail=f"Model '{model_id}' tidak terdaftar.")
    if not definition["path"].is_file():
        raise HTTPException(status_code=503, detail=f"Model belum tersedia: {definition['path'].name}")
    raw = await file.read()
    try:
        image = Image.open(BytesIO(raw)).convert("RGB")
    except (UnidentifiedImageError, OSError) as exc:
        raise HTTPException(status_code=400, detail="File bukan gambar yang valid.") from exc
    detections = []
    eff_threshold = float(np.clip(threshold, 0.10, 0.95))
    if definition["kind"] == "classification":
        cnn_sess, cnn_in, names = load_classifier()
        rgb_np = np_from_image(image)
        probabilities = None
        uniform_res = analyze_uniform_or_screen_leaf(rgb_np, size=128)
        if uniform_res is not None:
            st, _, big_spots, is_screen = uniform_res
            raw_probs = cnn_sess.run(None, {cnn_in: st})[0][0]
            probabilities = adjust_uniform_bg_leaf_probs(raw_probs, big_spots, names, is_screen=is_screen)

        if probabilities is None and YOLOV8_MODEL_PATH.is_file() and min(rgb_np.shape[:2]) >= 32:
            try:
                yolo_sess, yolo_in = _get_yolo_ort_session(YOLOV8_MODEL_PATH)
                h, w = rgb_np.shape[:2]
                cand_dets = [
                    d
                    for d in _infer_onnx_crop(yolo_sess, yolo_in, names[:6], rgb_np, 0.20, 0, 0, w, h)
                    if isinstance(d.get("bbox_xyxy"), list)
                    and is_valid_chili_leaf_box(rgb_np, d["bbox_xyxy"])  # type: ignore[arg-type]
                ]
                cand_dets = suppress_overlapping_detections(cand_dets, iou_threshold=0.45)
                if cand_dets:
                    top_dets = sorted(cand_dets, key=lambda d: float(d["confidence"]), reverse=True)[:3]
                    valid_leaf_preds = []
                    fallback_probs = None
                    for d in top_dets:
                        box_coords = d["bbox_xyxy"]
                        if isinstance(box_coords, list) and (box_coords[2] - box_coords[0]) >= 12 and (box_coords[3] - box_coords[1]) >= 12:
                            roi_0 = preprocess_cnn_5ch(crop_leaf_roi_with_padding(rgb_np, box_coords, pad_ratio=0.0), size=128)[0]
                            roi_8 = preprocess_cnn_5ch(crop_leaf_roi_with_padding(rgb_np, box_coords, pad_ratio=0.08), size=128)[0]
                            outs = cnn_sess.run(None, {cnn_in: np.stack([roi_0, roi_8], axis=0)})[0]
                            p = 0.35 * outs[0] + 0.65 * outs[1]
                            if fallback_probs is None:
                                fallback_probs = p
                            cid = int(p.argmax())
                            cname = names[cid] if 0 <= cid < len(names) else ""
                            if not is_non_leaf_class(cname):
                                valid_leaf_preds.append((p, float(d["confidence"])))
                    if valid_leaf_preds:
                        probabilities = max(
                            valid_leaf_preds,
                            key=lambda item: float(np.max(item[0])) * (0.75 + 0.25 * item[1]),
                        )[0]
                    elif fallback_probs is not None:
                        probabilities = fallback_probs
            except Exception:
                probabilities = None

        if probabilities is None:
            h, w = rgb_np.shape[:2]
            if is_valid_chili_leaf_box(rgb_np, [0.0, 0.0, float(w), float(h)]):
                input_tensor = preprocess_cnn_5ch(rgb_np, size=128)
                probabilities = cnn_sess.run(None, {cnn_in: input_tensor})[0][0]
            else:
                probabilities = np.zeros(len(names), dtype=np.float32)
                probabilities[-1] = 1.0

        class_id = int(probabilities.argmax())
        if is_non_leaf_class(names[class_id] if 0 <= class_id < len(names) else "") or float(probabilities[class_id]) < eff_threshold:
            _, contour_probs = detect_foliage_contour_fallback(
                rgb_np,
                names,
                eff_threshold,
                predict_batch_fn=lambda batch: cnn_sess.run(None, {cnn_in: batch})[0],
            )
            if contour_probs is not None:
                probabilities = contour_probs

        class_id = int(probabilities.argmax())
        score = round(float(probabilities[class_id]), 6)
        class_name = names[class_id] if 0 <= class_id < len(names) else f"Class {class_id}"
        if score >= eff_threshold and not is_non_leaf_class(class_name):
            classification = {
                "class_id": class_id,
                "class_name": class_name,
                "confidence": score,
            }
            count = 1
        else:
            classification = None
            count = 0
        return {
            "model_id": model_id,
            "model": definition["path"].name,
            "image_width": image.width,
            "image_height": image.height,
            "count": count,
            "classification": classification,
            "primary_detection": classification,
            "detections": [],
        }

    yolo_path, names = load_yolo_names(model_id)
    full_np = np_from_image(image)
    is_live_webcam = (file.filename or "").strip().lower() == "webcam.jpg"
    detections = run_onnx_yolo(yolo_path, names, full_np, eff_threshold, fast_mode=is_live_webcam)
    primary = select_primary_detection(detections)
    return {
        "model_id": model_id,
        "model": definition["path"].name,
        "image_width": image.width,
        "image_height": image.height,
        "count": len(detections),
        "primary_detection": primary,
        "detections": detections,
    }
