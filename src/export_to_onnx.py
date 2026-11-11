"""Export YOLOv8n (.pt) and 5-channel CNN (.keras) models to ONNX format for Laravel integration."""
from __future__ import annotations

import json
import shutil
from pathlib import Path
from ultralytics import YOLO

PROJECT_ROOT = Path(__file__).resolve().parents[1]
MODELS_DIR = PROJECT_ROOT / "models"
PUBLIC_MODELS_DIR = PROJECT_ROOT / "public" / "models"
DEFAULT_CLASS_NAMES = [
    "Bercak Bakteri",
    "Bercak Cercospora",
    "Bercak Putih",
    "Daun Sehat",
    "Defisiensi Nutrisi",
    "Virus Keriting",
    "Bukan Daun",
]


def format_class_name(class_name: str) -> str:
    trimmed = class_name.strip()
    if not trimmed:
        return ""
    if "_" in trimmed:
        return " ".join(trimmed.replace("_", " ").split()).title()
    return trimmed


def export_yolo_to_onnx(pt_filename: str) -> None:
    pt_path = MODELS_DIR / pt_filename
    if not pt_path.is_file():
        print(f"[Skip] {pt_filename} tidak ditemukan.")
        return

    print(f"Mengonversi {pt_filename} ke ONNX...")
    model = YOLO(str(pt_path))
    onnx_str = model.export(format="onnx", imgsz=640, simplify=False)
    onnx_path = Path(onnx_str)

    names = [format_class_name(str(model.names[idx])) for idx in sorted(model.names)]
    meta_path = onnx_path.with_suffix(".names.json")
    meta_path.write_text(json.dumps(names, ensure_ascii=False, indent=2), encoding="utf-8")

    PUBLIC_MODELS_DIR.mkdir(parents=True, exist_ok=True)
    shutil.copy2(onnx_path, PUBLIC_MODELS_DIR / onnx_path.name)
    shutil.copy2(meta_path, PUBLIC_MODELS_DIR / meta_path.name)
    print(f"[OK] Berhasil dikonversi: {onnx_path.name} & disalin ke public/models/{onnx_path.name}")


def export_cnn_5ch_to_onnx(keras_filename: str = "cnn_5ch_7kelas_hard_negative.keras") -> None:
    keras_path = MODELS_DIR / keras_filename
    if not keras_path.is_file():
        print(f"[Skip] {keras_filename} tidak ditemukan.")
        return

    import tensorflow as tf
    import tf2onnx

    eval_path = MODELS_DIR / "hasil_evaluasi_cnn_7kelas.json"
    if eval_path.is_file():
        eval_cfg = json.loads(eval_path.read_text(encoding="utf-8"))
        names = [format_class_name(str(item)) for item in eval_cfg.get("class_names", DEFAULT_CLASS_NAMES)]
        img_size = int(eval_cfg.get("image_size", 128))
    else:
        names = DEFAULT_CLASS_NAMES
        img_size = 128

    print(f"Mengonversi {keras_filename} ({img_size}x{img_size}x5) ke ONNX...")
    model = tf.keras.models.load_model(str(keras_path), compile=False)
    onnx_path = keras_path.with_suffix(".onnx")
    spec = (tf.TensorSpec((None, img_size, img_size, 5), tf.float32, name="input"),)
    tf2onnx.convert.from_keras(model, input_signature=spec, opset=13, output_path=str(onnx_path))

    meta_path = onnx_path.with_suffix(".names.json")
    meta_path.write_text(json.dumps(names, ensure_ascii=False, indent=2), encoding="utf-8")

    PUBLIC_MODELS_DIR.mkdir(parents=True, exist_ok=True)
    shutil.copy2(onnx_path, PUBLIC_MODELS_DIR / onnx_path.name)
    shutil.copy2(meta_path, PUBLIC_MODELS_DIR / meta_path.name)
    print(f"[OK] Berhasil dikonversi: {onnx_path.name} & disalin ke public/models/{onnx_path.name}")


if __name__ == "__main__":
    export_yolo_to_onnx("yolov8n_chili.pt")
    export_cnn_5ch_to_onnx("cnn_5ch_7kelas_hard_negative.keras")
