"""Fast ONNX Runtime CLI inference for Laravel backend (YOLOv8n, CNN 5-Channel)."""
from __future__ import annotations

import argparse
import json
import os
import sys
import warnings

os.environ["TF_CPP_MIN_LOG_LEVEL"] = "3"
warnings.filterwarnings("ignore")

from pathlib import Path
import cv2
import numpy as np
from PIL import Image

PROJECT_ROOT = Path(__file__).resolve().parents[1]
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
    """Format label penyakit agar konsisten tanpa underscore (mis. 'bercak_daun' -> 'Bercak Daun')."""
    trimmed = class_name.strip()
    if not trimmed:
        return ""
    if "_" in trimmed:
        return " ".join(trimmed.replace("_", " ").split()).title()
    return trimmed
MODEL_DEFINITIONS = {
    "yolov8n-chili": {
        "name": "Yolov8n (Deteksi Penyakit Daun Cabai)",
        "path": PROJECT_ROOT / "models" / "yolov8n_chili.onnx",
        "names_path": PROJECT_ROOT / "models" / "yolov8n_chili.names.json",
        "kind": "onnx_yolo",
    },
    "cnn-5ch-chili": {
        "name": "CNN (Klasifikasi Penyakit Daun Cabai)",
        "path": PROJECT_ROOT / "models" / "cnn_5ch_7kelas_hard_negative.onnx",
        "names_path": PROJECT_ROOT / "models" / "cnn_5ch_7kelas_hard_negative.names.json",
        "kind": "classification_onnx",
    },
}


_K_OPEN = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5))
_K_CLOSE = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (7, 7))
_CLAHE = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))


def is_non_leaf_class(class_name: str) -> bool:
    """Return True if the predicted class represents the non-leaf hard-negative class."""
    normalized = " ".join(class_name.strip().lower().replace("_", " ").split())
    return normalized in {"bukan daun", "non leaf", "non-leaf"}


def is_healthy_leaf_class(class_name: str) -> bool:
    """Return True if the predicted class represents a healthy leaf."""
    normalized = " ".join(class_name.strip().lower().replace("_", " ").split())
    return bool(normalized) and ("sehat" in normalized or normalized == "healthy")


def select_primary_detection(detections: list[dict[str, object]]) -> dict[str, object] | None:
    """Prefer the highest-confidence diseased detection over healthy leaves."""
    valid = [item for item in detections if not is_non_leaf_class(str(item.get("class_name", "")))]
    if not valid:
        return None
    diseased = [item for item in valid if not is_healthy_leaf_class(str(item.get("class_name", "")))]
    candidates = diseased if diseased else valid
    return max(candidates, key=lambda item: float(item.get("confidence", 0.0)))


def preprocess_cnn_5ch(rgb_np: np.ndarray, size: int = 128) -> np.ndarray:
    """Build (1, 128, 128, 5) float32 tensor (0-255): Segmented RGB + CLAHE Gray + Canny Edge."""
    cv2.setRNGSeed(42)
    crop_res = cv2.resize(rgb_np, (size, size), interpolation=cv2.INTER_AREA)

    mask = np.full((size, size), cv2.GC_PR_BGD, dtype=np.uint8)
    mask[:8, :8] = cv2.GC_BGD
    mask[:8, -8:] = cv2.GC_BGD
    mask[-8:, :8] = cv2.GC_BGD
    mask[-8:, -8:] = cv2.GC_BGD
    m1, m2 = int(size * 0.15), int(size * 0.85)
    mask[m1:m2, m1:m2] = cv2.GC_PR_FGD
    c1, c2 = int(size * 0.35), int(size * 0.65)
    mask[c1:c2, c1:c2] = cv2.GC_FGD

    bgd = np.zeros((1, 65), np.float64)
    fgd = np.zeros((1, 65), np.float64)
    try:
        cv2.grabCut(crop_res, mask, None, bgd, fgd, 3, cv2.GC_INIT_WITH_MASK)
        leaf_mask = np.where(
            (mask == cv2.GC_BGD) | (mask == cv2.GC_PR_BGD), 0, 255
        ).astype(np.uint8)
        leaf_mask = cv2.morphologyEx(leaf_mask, cv2.MORPH_OPEN, _K_OPEN)
        leaf_mask = cv2.morphologyEx(leaf_mask, cv2.MORPH_CLOSE, _K_CLOSE)

        contours, _ = cv2.findContours(
            leaf_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE
        )
        if contours:
            max_area = max(cv2.contourArea(cnt) for cnt in contours)
            clean_mask = np.zeros_like(leaf_mask)
            for cnt in contours:
                if cv2.contourArea(cnt) >= max_area * 0.15:
                    cv2.drawContours(clean_mask, [cnt], -1, 255, thickness=cv2.FILLED)
            leaf_mask = (cv2.GaussianBlur(clean_mask, (5, 5), 0) > 127).astype(
                np.uint8
            ) * 255
    except Exception:
        leaf_mask = np.full((size, size), 255, dtype=np.uint8)

    if (leaf_mask > 0).mean() < 0.08:
        leaf_mask = np.full((size, size), 255, dtype=np.uint8)

    interior_mask = cv2.erode(leaf_mask, _K_OPEN, iterations=1)
    seg_rgb = cv2.bitwise_and(crop_res, crop_res, mask=leaf_mask)

    # Hitung CLAHE pada crop_res sebelum masking agar piksel background (0) tidak mendistorsi histogram lokal
    gray = cv2.cvtColor(crop_res, cv2.COLOR_RGB2GRAY)
    clahe_gray = _CLAHE.apply(gray)
    clahe_gray = cv2.bitwise_and(clahe_gray, clahe_gray, mask=leaf_mask)
    canny = cv2.Canny(cv2.GaussianBlur(clahe_gray, (3, 3), 0), 50, 150)
    canny = cv2.bitwise_and(canny, canny, mask=interior_mask)

    stack = np.dstack([seg_rgb, clahe_gray, canny]).astype(np.float32)
    return stack[None, ...]


def crop_leaf_roi_with_padding(
    rgb_np: np.ndarray, bbox_xyxy: list[float], pad_ratio: float = 0.08
) -> np.ndarray:
    """Crop leaf bounding box with 8% margin matching Training_CNN_7_Kelas_HardNegative.ipynb."""
    h, w = rgb_np.shape[:2]
    x1, y1, x2, y2 = bbox_xyxy
    bw, bh = max(1.0, x2 - x1), max(1.0, y2 - y1)
    pad_w, pad_h = bw * pad_ratio, bh * pad_ratio
    cx1 = max(0, int(round(x1 - pad_w)))
    cy1 = max(0, int(round(y1 - pad_h)))
    cx2 = min(w, int(round(x2 + pad_w)))
    cy2 = min(h, int(round(y2 + pad_h)))
    crop = rgb_np[cy1:cy2, cx1:cx2]
    return crop if crop.size > 0 else rgb_np


def is_valid_chili_leaf_box(
    rgb: np.ndarray,
    bbox_xyxy: list[float],
    class_name: str = "",
    confidence: float = 0.0,
) -> bool:
    """Verify that a candidate bounding box contains genuine chili leaf tissue (supporting pale white-spotted leaves)."""
    h, w = rgb.shape[:2]
    if len(bbox_xyxy) != 4:
        return False
    x1, y1, x2, y2 = bbox_xyxy
    ix1, iy1 = max(0, int(round(x1))), max(0, int(round(y1)))
    ix2, iy2 = min(w, int(round(x2))), min(h, int(round(y2)))
    if (ix2 - ix1) < 12 or (iy2 - iy1) < 12:
        return False
    crop = rgb[iy1:iy2, ix1:ix2]
    hsv = cv2.cvtColor(crop, cv2.COLOR_RGB2HSV)
    r, g, b = crop[:, :, 0].astype(np.int16), crop[:, :, 1].astype(np.int16), crop[:, :, 2].astype(np.int16)
    hsv_h, hsv_s, hsv_v = hsv[:, :, 0], hsv[:, :, 1], hsv[:, :, 2]

    # Daun bercak putih berat (powdery mildew) di kamera indoor cenderung pucat/putih sehingga saturasinya rendah
    cname_norm = class_name.strip().lower().replace("_", " ")
    if "putih" in cname_norm and confidence >= 0.50:
        non_skin_leaf = (g >= r - 10) & (hsv_v >= 28)
        return bool(non_skin_leaf.mean() >= 0.35 and float(np.std(hsv_v)) >= 10.0)

    if float(hsv_s.mean()) < 26.0:
        return False
    foliage_px = (hsv_s >= 25) & (hsv_v >= 25) & (g >= r - 20) & ((g > b + 2) | ((hsv_h >= 18) & (hsv_h <= 95)))
    chlorophyll_px = (hsv_s >= 28) & (hsv_v >= 26) & ((g - r) >= 3) & ((g - b) >= 3)
    return bool(foliage_px.mean() >= 0.14 and chlorophyll_px.mean() >= 0.05)


def _analyze_uniform_canvas(
    rgb: np.ndarray, size: int = 128, is_screen: bool = False
) -> tuple[np.ndarray, list[list[float]], int] | None:
    """Segment and normalize a solitary chili leaf on a uniform or monitor-screen canvas."""
    h, w = rgb.shape[:2]
    if h < 32 or w < 32:
        return None
    hsv = cv2.cvtColor(rgb, cv2.COLOR_RGB2HSV)
    lab = cv2.cvtColor(rgb, cv2.COLOR_RGB2LAB).astype(np.float32)
    by, bx = max(4, int(h * 0.08)), max(4, int(w * 0.08))
    r, g, b = rgb[:, :, 0].astype(np.int16), rgb[:, :, 1].astype(np.int16), rgb[:, :, 2].astype(np.int16)

    strips = [
        (slice(0, by), slice(None)),
        (slice(-by, None), slice(None)),
        (slice(None), slice(0, bx)),
        (slice(None), slice(-bx, None)),
    ]
    uniform_l, uniform_a, uniform_b = [], [], []
    ab_tol = 12.0 if is_screen else 14.0
    l_tol = 36.0 if is_screen else 28.0
    for sy, sx in strips:
        sl, sa, sb = lab[sy, sx, 0], lab[sy, sx, 1], lab[sy, sx, 2]
        sg = ((g[sy, sx] - r[sy, sx]) > 8) & ((g[sy, sx] - b[sy, sx]) > 8)
        if (sa.std() + sb.std()) <= ab_tol and sl.std() <= l_tol and sg.mean() <= 0.20:
            uniform_l.append(sl.ravel())
            uniform_a.append(sa.ravel())
            uniform_b.append(sb.ravel())
    if len(uniform_l) < 3:
        return None

    b_l = np.concatenate(uniform_l)
    b_a = np.concatenate(uniform_a)
    b_b = np.concatenate(uniform_b)
    if not is_screen and ((b_a.std() + b_b.std()) > 16.0 or b_l.std() > 30.0):
        return None

    med_l, med_a, med_b = np.median(b_l), np.median(b_a), np.median(b_b)
    pm = None
    pm_closed = None
    if not is_screen:
        bg_dist = np.sqrt(
            0.25 * (lab[:, :, 0] - med_l) ** 2 + (lab[:, :, 1] - med_a) ** 2 + (lab[:, :, 2] - med_b) ** 2
        )
        fg_color = (hsv[:, :, 1] > 18) & (((g - b) >= 2) | ((r - b) > 10)) & (g > r - 30)
        pm_raw = ((bg_dist > 18.0) & fg_color).astype(np.uint8) * 255
        pm = cv2.morphologyEx(pm_raw, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5)))
        pm_closed = cv2.morphologyEx(pm, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (15, 15)))
        pr = (pm_closed > 0).mean()
        green_px = (pm_closed > 0) & ((g - r) >= 4) & ((g - b) >= 2) & (hsv[:, :, 1] >= 20)
        if not (0.012 <= pr <= 0.65) or green_px.sum() < (pm_closed > 0).sum() * 0.35:
            pm_closed = None

    if pm_closed is None:
        chroma_d = np.sqrt((lab[:, :, 1] - med_a) ** 2 + (lab[:, :, 2] - med_b) ** 2)
        c_thresh = 4.5 if is_screen else 5.0
        s_min = 16 if is_screen else 18
        pm_raw = (
            (chroma_d > c_thresh) & (hsv[:, :, 1] >= s_min) & ((g - b) >= 4) & (g >= r - 25)
        ).astype(np.uint8) * 255
        pm = cv2.morphologyEx(pm_raw, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5)))
        close_k = 17 if is_screen else 15
        pm_closed = cv2.morphologyEx(
            pm, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (close_k, close_k))
        )
        pr = (pm_closed > 0).mean()
        green_px = (pm_closed > 0) & ((g - r) >= 2) & ((g - b) >= 4) & (hsv[:, :, 1] >= s_min)
        if not (0.012 <= pr <= 0.65) or green_px.sum() < (pm_closed > 0).sum() * 0.35:
            return None

    contours, _ = cv2.findContours(pm_closed, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return None
    main_cnt = max(contours, key=cv2.contourArea)
    max_a = cv2.contourArea(main_cnt)
    if max_a < (0.02 * h * w if is_screen else 400.0):
        return None

    filled = np.zeros_like(pm_closed)
    cv2.drawContours(filled, [main_cnt], -1, 255, thickness=cv2.FILLED)
    if is_screen:
        sol_cnt = max_a / max(1.0, cv2.contourArea(cv2.convexHull(main_cnt)))
        interior_mask = cv2.erode(filled, _K_OPEN, iterations=2)
        v_region = hsv[:, :, 2][interior_mask > 0] if (interior_mask > 0).any() else hsv[:, :, 2][filled > 0]
        v_std = float(np.std(v_region))
        if not (0.68 <= sol_cnt <= 0.96) or v_std < 6.0:
            return None

    valid_cnts = [c for c in contours if cv2.contourArea(c) >= max(400.0, max_a * 0.20)]
    boxes: list[list[float]] = []
    for c in valid_cnts:
        x, y, bw, bh = cv2.boundingRect(c)
        boxes.append([round(float(x), 2), round(float(y), 2), round(float(x + bw), 2), round(float(y + bh), 2)])
    if not boxes:
        return None

    blade_mask = cv2.morphologyEx(filled, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (31, 31)))
    if (blade_mask > 0).sum() < 1000:
        blade_mask = filled
    b_cnts, _ = cv2.findContours(blade_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not b_cnts:
        return None
    b_cnt = max(b_cnts, key=cv2.contourArea)
    hull_a = max(1.0, cv2.contourArea(cv2.convexHull(b_cnt)))
    conv_sol = cv2.contourArea(b_cnt) / hull_a

    if conv_sol >= 0.85:
        (cx, cy), (rw, rh), angle = cv2.minAreaRect(b_cnt)
        if rw < rh:
            angle += 90.0
        M = cv2.getRotationMatrix2D((cx, cy), angle, 1.0)
        rot_rgb = cv2.warpAffine(rgb, M, (w, h), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)
        rot_m = cv2.warpAffine(blade_mask, M, (w, h), flags=cv2.INTER_NEAREST)
        ys, xs = np.where(rot_m > 0)
        if len(ys) == 0:
            return None
        y1, y2, x1, x2 = ys.min(), ys.max(), xs.min(), xs.max()
        crop = rot_rgb[y1 : y2 + 1, x1 : x2 + 1]
        crop_m = rot_m[y1 : y2 + 1, x1 : x2 + 1]

        img = cv2.resize(crop, (size, size), interpolation=cv2.INTER_AREA).astype(np.float32)
        m128 = (cv2.resize(crop_m, (size, size), interpolation=cv2.INTER_NEAREST) > 127).astype(np.uint8) * 255
        if is_screen:
            interior_l = cv2.erode(m128, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (7, 7)), iterations=1)
            lesion_cnt_mask = (img[:, :, 0] >= img[:, :, 1] - 4) & (interior_l > 0)
            min_spot_area = 16
        else:
            lesion_cnt_mask = (img[:, :, 0] >= img[:, :, 1] - 4) & (m128 > 0)
            min_spot_area = 18
        lesion = (img[:, :, 0] >= img[:, :, 1] - 4) & (m128 > 0)
        lesion_open = cv2.morphologyEx(
            lesion_cnt_mask.astype(np.uint8) * 255,
            cv2.MORPH_OPEN,
            cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3)),
        )
        num_l, _, stats, _ = cv2.connectedComponentsWithStats(lesion_open)
        big_spots = sum(1 for i in range(1, num_l) if stats[i, cv2.CC_STAT_AREA] >= min_spot_area)

        for c, (t_m, t_s) in enumerate([(140.0, 48.0), (175.0, 38.0), (45.0, 35.0)]):
            vals = img[:, :, c][m128 > 0]
            if len(vals) > 10:
                img[:, :, c] = np.clip((img[:, :, c] - vals.mean()) / (vals.std() + 1e-5) * t_s + t_m, 0, 255)
        if lesion.sum() >= (m128 > 0).sum() * 0.015:
            img[:, :, 1] = np.where(lesion, img[:, :, 1] * 0.45, img[:, :, 1])
            img[:, :, 2] = np.where(lesion, img[:, :, 2] * 0.40, img[:, :, 2])
            img[:, :, 0] = np.where(lesion, np.clip(img[:, :, 0] * 0.75, 40, 160), img[:, :, 0])
        img_u8 = img.astype(np.uint8)
        seg = cv2.bitwise_and(img_u8, img_u8, mask=m128)
        gray = cv2.cvtColor(img_u8, cv2.COLOR_RGB2GRAY)
        clahe = cv2.bitwise_and(_CLAHE.apply(gray), m128)
        inter = cv2.erode(m128, _K_OPEN, iterations=1)
        canny = cv2.bitwise_and(cv2.Canny(cv2.GaussianBlur(clahe, (3, 3), 0), 40, 120), inter)
        return np.dstack([seg, clahe, canny]).astype(np.float32)[None, ...], boxes, big_spots

    ys_all, xs_all = np.where(pm > 0)
    if len(ys_all) == 0:
        return None
    by1, by2, bx1, bx2 = ys_all.min(), ys_all.max(), xs_all.min(), xs_all.max()
    crop = rgb[by1 : by2 + 1, bx1 : bx2 + 1]
    crop_pm = pm[by1 : by2 + 1, bx1 : bx2 + 1]
    img = cv2.resize(crop, (size, size), interpolation=cv2.INTER_AREA).astype(np.float32)
    m128 = (cv2.resize(crop_pm, (size, size), interpolation=cv2.INTER_NEAREST) > 127).astype(np.uint8) * 255
    for c, (t_m, t_s) in enumerate([(155.0, 50.0), (175.0, 42.0), (35.0, 35.0)]):
        vals = img[:, :, c][m128 > 0]
        if len(vals) > 10:
            img[:, :, c] = np.clip((img[:, :, c] - vals.mean()) / (vals.std() + 1e-5) * t_s + t_m, 0, 255)
    img_u8 = img.astype(np.uint8)
    seg = cv2.bitwise_and(img_u8, img_u8, mask=m128)
    gray = cv2.cvtColor(img_u8, cv2.COLOR_RGB2GRAY)
    clahe = cv2.bitwise_and(_CLAHE.apply(gray), m128)
    canny = cv2.bitwise_and(cv2.Canny(clahe, 35, 110), m128)
    return np.dstack([seg, clahe, canny]).astype(np.float32)[None, ...], boxes, 0


def analyze_uniform_or_screen_leaf(
    rgb: np.ndarray, size: int = 128
) -> tuple[np.ndarray, list[list[float]], int, bool] | None:
    """Analyze a solitary chili leaf on a uniform surface or inside a photographed monitor screen."""
    direct = _analyze_uniform_canvas(rgb, size=size, is_screen=False)
    if direct is not None:
        return direct[0], direct[1], direct[2], False

    h, w = rgb.shape[:2]
    if h < 120 or w < 120:
        return None
    hsv = cv2.cvtColor(rgb, cv2.COLOR_RGB2HSV)
    sp = ((hsv[:, :, 1] < 48) & (hsv[:, :, 2] > 60)).astype(np.uint8) * 255
    sp = cv2.morphologyEx(sp, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_RECT, (25, 25)))
    cnts, _ = cv2.findContours(sp, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not cnts:
        return None
    mc = max(cnts, key=cv2.contourArea)
    if cv2.contourArea(mc) < 0.20 * h * w:
        return None
    sx, sy, sw, sh = cv2.boundingRect(mc)
    ix1, iy1 = sx + int(sw * 0.04), sy + int(sh * 0.04)
    ix2, iy2 = sx + sw - int(sw * 0.04), sy + sh - int(sh * 0.04)
    if (ix2 - ix1) < 100 or (iy2 - iy1) < 100:
        return None

    screen_res = _analyze_uniform_canvas(rgb[iy1:iy2, ix1:ix2], size=size, is_screen=True)
    if screen_res is None:
        return None
    st, rel_boxes, big_spots = screen_res
    abs_boxes = [
        [round(bx1 + ix1, 2), round(by1 + iy1, 2), round(bx2 + ix1, 2), round(by2 + iy1, 2)]
        for bx1, by1, bx2, by2 in rel_boxes
    ]
    return st, abs_boxes, big_spots, True


def analyze_uniform_bg_leaf(
    rgb: np.ndarray, size: int = 128
) -> tuple[np.ndarray, list[list[float]], int] | None:
    """Backward-compatible wrapper returning tensor, boxes, and lesion count."""
    res = analyze_uniform_or_screen_leaf(rgb, size=size)
    return (res[0], res[1], res[2]) if res is not None else None


def adjust_uniform_bg_leaf_probs(
    probs: np.ndarray, big_spots: int, names: list[str], is_screen: bool = False
) -> np.ndarray:
    """Refine brown-spot probabilities for uniform/screen leaf detections while preserving Bukan Daun guards."""
    non_leaf_prob = float(probs[6]) if len(probs) > 6 else 0.0
    if not is_screen and non_leaf_prob >= 0.50:
        return probs
    out = probs.copy()
    if is_screen and len(out) > 6:
        # Leaf shape & chlorophyll verified organically by _analyze_uniform_canvas(is_screen=True)
        out[6] = 0.0
        leaf_sum = float(out[:6].sum())
        if leaf_sum > 1e-6:
            out[:6] = out[:6] / leaf_sum
    if 1 <= big_spots <= 5 and int(np.argmax(out[:6])) in (0, 1):
        out[1] = float(out[0] + out[1])
        out[0] = 0.0
    return out


def preprocess_white_bg_leaf(rgb: np.ndarray, size: int = 128) -> np.ndarray | None:
    """Backward-compatible wrapper returning the 5-channel tensor for uniform-background leaves."""
    res = analyze_uniform_bg_leaf(rgb, size=size)
    return res[0] if res is not None else None


_CNN_ORT_CACHE: tuple[object, str] | None = None
_YOLO_ORT_CACHE: dict[str, tuple[object, str]] = {}


def _get_yolo_ort_session(onnx_path: Path) -> tuple[object, str]:
    key = str(onnx_path.resolve())
    cached = _YOLO_ORT_CACHE.get(key)
    if cached is not None:
        return cached
    import onnxruntime as ort

    sess = ort.InferenceSession(str(onnx_path), providers=["CPUExecutionProvider"])
    pair = (sess, sess.get_inputs()[0].name)
    _YOLO_ORT_CACHE[key] = pair
    return pair


def _get_cnn_ort_session() -> tuple[object, str] | None:
    global _CNN_ORT_CACHE
    if _CNN_ORT_CACHE is not None:
        return _CNN_ORT_CACHE
    cnn_def = MODEL_DEFINITIONS.get("cnn-5ch-chili")
    cnn_path: Path | None = cnn_def["path"] if cnn_def else None  # type: ignore[assignment]
    if cnn_path is None or not cnn_path.is_file():
        return None
    import onnxruntime as ort

    sess = ort.InferenceSession(str(cnn_path), providers=["CPUExecutionProvider"])
    _CNN_ORT_CACHE = (sess, sess.get_inputs()[0].name)
    return _CNN_ORT_CACHE


def detect_foliage_contour_fallback(
    rgb: np.ndarray,
    names: list[str],
    conf_threshold: float,
    predict_batch_fn: object | None = None,
) -> tuple[list[dict[str, object]], np.ndarray | None]:
    """Locate foliage contours on cluttered/indoor/macro backgrounds and verify via 7-class CNN (rejecting Bukan Daun)."""
    h, w = rgb.shape[:2]
    if h < 64 or w < 64:
        return [], None
    hsv = cv2.cvtColor(rgb, cv2.COLOR_RGB2HSV)
    r, g, b = rgb[:, :, 0].astype(np.int16), rgb[:, :, 1].astype(np.int16), rgb[:, :, 2].astype(np.int16)
    fmask = (
        (hsv[:, :, 0] >= 24)
        & (hsv[:, :, 0] <= 90)
        & (hsv[:, :, 1] >= 32)
        & (hsv[:, :, 2] >= 28)
        & (g >= r + 2)
        & (g >= b + 4)
    ).astype(np.uint8) * 255
    fmask = cv2.morphologyEx(fmask, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5)))
    fmask = cv2.morphologyEx(fmask, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (17, 17)))
    cnts, _ = cv2.findContours(fmask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    min_area = max(6000.0, 0.03 * float(h * w))
    cand_cnts = sorted(
        [c for c in cnts if cv2.contourArea(c) >= min_area],
        key=cv2.contourArea,
        reverse=True,
    )[:2]
    if not cand_cnts:
        return [], None

    cand_contours: list[tuple[np.ndarray, list[float], bool]] = []
    tensors: list[np.ndarray] = []
    norm_tensors: list[np.ndarray] = []
    for c in cand_cnts:
        x, y, bw, bh = cv2.boundingRect(c)
        box = [round(float(x), 2), round(float(y), 2), round(float(x + bw), 2), round(float(y + bh), 2)]
        if is_valid_chili_leaf_box(rgb, box):
            m_full = np.zeros((h, w), dtype=np.uint8)
            cv2.drawContours(m_full, [c], -1, 255, thickness=cv2.FILLED)
            mask_b = m_full > 0
            hull_a = max(1.0, cv2.contourArea(cv2.convexHull(c)))
            sol = float(cv2.contourArea(c)) / hull_a
            aspect = float(max(bw, bh)) / max(1.0, float(min(bw, bh)))
            h_med = float(np.median(hsv[:, :, 0][mask_b]))
            s_med = float(np.median(hsv[:, :, 1][mask_b]))
            v_std = float(np.std(hsv[:, :, 2][mask_b]))
            gr_med = float(np.median(g[mask_b] - r[mask_b]))
            gb_med = float(np.median(g[mask_b] - b[mask_b]))
            # Hue klorofil daun alami berada di 24..64 (menolak dinding cat hijau-mint >= 68)
            strong_chlorophyll_blade = (
                24.0 <= h_med <= 64.0
                and sol >= 0.55
                and aspect <= 3.8
                and s_med >= 38.0
                and v_std >= 12.0
                and gr_med >= 4.0
                and gb_med >= 12.0
            )
            cand_contours.append((c, box, strong_chlorophyll_blade))
            tensors.append(preprocess_cnn_5ch(crop_leaf_roi_with_padding(rgb, box, pad_ratio=0.08), size=128)[0])

            crop_rgb = crop_leaf_roi_with_padding(rgb, box, pad_ratio=0.04)
            crop_m = crop_leaf_roi_with_padding(m_full, box, pad_ratio=0.04)
            img_f = cv2.resize(crop_rgb, (128, 128), interpolation=cv2.INTER_AREA).astype(np.float32)
            m128 = (cv2.resize(crop_m, (128, 128), interpolation=cv2.INTER_NEAREST) > 127).astype(np.uint8) * 255
            for ch, (t_m, t_s) in enumerate([(140.0, 48.0), (175.0, 38.0), (45.0, 35.0)]):
                vals = img_f[:, :, ch][m128 > 0]
                if len(vals) > 10:
                    img_f[:, :, ch] = np.clip((img_f[:, :, ch] - vals.mean()) / (vals.std() + 1e-5) * t_s + t_m, 0, 255)
            img_nu8 = img_f.astype(np.uint8)
            seg_n = cv2.bitwise_and(img_nu8, img_nu8, mask=m128)
            gray_n = cv2.cvtColor(img_nu8, cv2.COLOR_RGB2GRAY)
            clahe_n = cv2.bitwise_and(_CLAHE.apply(gray_n), m128)
            canny_n = cv2.bitwise_and(cv2.Canny(cv2.GaussianBlur(clahe_n, (3, 3), 0), 40, 120), cv2.erode(m128, _K_OPEN, iterations=1))
            norm_tensors.append(np.dstack([seg_n, clahe_n, canny_n]).astype(np.float32))
    if not cand_contours:
        return [], None

    batch = np.stack(tensors, axis=0)
    norm_batch = np.stack(norm_tensors, axis=0)
    if predict_batch_fn is not None:
        probs_batch = predict_batch_fn(batch)  # type: ignore[operator]
        norm_probs_batch = predict_batch_fn(norm_batch)  # type: ignore[operator]
    else:
        cnn_pair = _get_cnn_ort_session()
        if cnn_pair is None:
            return [], None
        cnn_sess, cnn_in = cnn_pair
        probs_batch = cnn_sess.run(None, {cnn_in: batch})[0]  # type: ignore[attr-defined]
        norm_probs_batch = cnn_sess.run(None, {cnn_in: norm_batch})[0]  # type: ignore[attr-defined]

    dets: list[dict[str, object]] = []
    best_probs: np.ndarray | None = None
    best_score = -1.0
    for (_, box, strong_blade), probs_gc, probs_norm in zip(cand_contours, probs_batch, norm_probs_batch):
        probs_arr = np.asarray(probs_gc, dtype=np.float32)
        norm_arr = np.asarray(probs_norm, dtype=np.float32)
        cid_gc = int(np.argmax(probs_arr))
        cname_gc = names[cid_gc] if 0 <= cid_gc < len(names) else f"Class {cid_gc}"
        non_leaf_gc = float(probs_arr[6]) if len(probs_arr) > 6 else 0.0
        # Jika GrabCut menolak karena pencahayaan indoor redup/meredup, gunakan prediksi blade daun yang sudah dinormalisasi
        if strong_blade and ((len(probs_arr) > 6 and cid_gc == 6) or is_non_leaf_class(cname_gc) or non_leaf_gc >= 0.50):
            probs_arr = norm_arr

        non_leaf_prob = float(probs_arr[6]) if len(probs_arr) > 6 else 0.0
        cid = int(np.argmax(probs_arr))
        score = float(probs_arr[cid])
        cname = names[cid] if 0 <= cid < len(names) else f"Class {cid}"
        if len(probs_arr) > 6 and cid == 6:
            continue
        if is_non_leaf_class(cname) or non_leaf_prob >= 0.50 or score < conf_threshold:
            continue
        dets.append(
            {
                "class_id": cid,
                "class_name": cname,
                "confidence": round(score, 6),
                "bbox_xyxy": box,
            }
        )
        if score > best_score:
            best_score = score
            best_probs = probs_arr
    return dets, best_probs


def detect_white_bg_yolo_boxes(
    rgb: np.ndarray, names: list[str], conf_threshold: float
) -> list[dict[str, object]] | None:
    """Return exact leaf contour bounding box(es) and disease class on uniform/screen photos, avoiding background false positives."""
    res = analyze_uniform_or_screen_leaf(rgb, size=128)
    if res is None:
        return None
    st, boxes, big_spots, is_screen = res
    cnn_pair = _get_cnn_ort_session()
    if cnn_pair is None:
        return None
    cnn_sess, cnn_in = cnn_pair
    raw_probs = cnn_sess.run(None, {cnn_in: st})[0][0]  # type: ignore[attr-defined]
    non_leaf_prob = float(raw_probs[6]) if len(raw_probs) > 6 else max(0.0, 1.0 - float(raw_probs[:6].sum()))
    if not is_screen and non_leaf_prob >= 0.50:
        return []
    probs = adjust_uniform_bg_leaf_probs(raw_probs, big_spots, names, is_screen=is_screen)
    leaf_probs = probs[:6]
    leaf_sum = max(1e-6, float(leaf_probs.sum()))
    cid = int(np.argmax(leaf_probs))
    if cid in (0, 1):
        score = float((leaf_probs[0] + leaf_probs[1]) / leaf_sum)
    else:
        score = float(leaf_probs[cid] / leaf_sum)
    if score < conf_threshold:
        return []
    cname = names[cid] if 0 <= cid < len(names) else f"Class {cid}"
    if is_non_leaf_class(cname):
        return []
    dets: list[dict[str, object]] = []
    for box in boxes:
        dets.append(
            {
                "class_id": cid,
                "class_name": cname,
                "confidence": round(score, 6),
                "bbox_xyxy": box,
            }
        )
    return dets



def suppress_overlapping_detections(
    detections: list[dict[str, object]], iou_threshold: float = 0.45
) -> list[dict[str, object]]:
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


def _infer_onnx_crop(
    session: object,
    input_name: str,
    names: list[str],
    crop_rgb: np.ndarray,
    conf_threshold: float,
    offset_x: int = 0,
    offset_y: int = 0,
    full_w: int = 0,
    full_h: int = 0,
) -> list[dict[str, object]]:
    crop_h, crop_w = crop_rgb.shape[:2]
    target_size = 640
    scale = min(target_size / crop_w, target_size / crop_h)
    new_w, new_h = int(round(crop_w * scale)), int(round(crop_h * scale))
    pad_w = (target_size - new_w) / 2.0
    pad_h = (target_size - new_h) / 2.0

    resized = cv2.resize(crop_rgb, (new_w, new_h), interpolation=cv2.INTER_LINEAR)
    top, bottom = int(round(pad_h - 0.1)), int(round(pad_h + 0.1))
    left, right = int(round(pad_w - 0.1)), int(round(pad_w + 0.1))
    padded = cv2.copyMakeBorder(
        resized, top, bottom, left, right, cv2.BORDER_CONSTANT, value=(114, 114, 114)
    )

    blob = padded.transpose((2, 0, 1))[None, ...].astype(np.float32) / 255.0
    outputs = session.run(None, {input_name: blob})[0]  # type: ignore[attr-defined]

    preds = np.squeeze(outputs, axis=0).T
    boxes_xywh = preds[:, :4]
    class_scores = preds[:, 4:]

    class_ids = np.argmax(class_scores, axis=1)
    max_scores = class_scores[np.arange(len(class_scores)), class_ids]

    mask = max_scores >= conf_threshold
    boxes_xywh = boxes_xywh[mask]
    max_scores = max_scores[mask]
    class_ids = class_ids[mask]

    max_w = full_w or crop_w
    max_h = full_h or crop_h
    detections: list[dict[str, object]] = []
    for (cx, cy, w, h), score, cls_id in zip(boxes_xywh, max_scores, class_ids):
        x1 = (cx - w / 2.0 - left) / scale + offset_x
        y1 = (cy - h / 2.0 - top) / scale + offset_y
        x2 = (cx + w / 2.0 - left) / scale + offset_x
        y2 = (cy + h / 2.0 - top) / scale + offset_y

        x1 = float(np.clip(x1, 0, max_w))
        y1 = float(np.clip(y1, 0, max_h))
        x2 = float(np.clip(x2, 0, max_w))
        y2 = float(np.clip(y2, 0, max_h))
        if (x2 - x1) < 4.0 or (y2 - y1) < 4.0:
            continue

        cid = int(cls_id)
        cname = names[cid] if 0 <= cid < len(names) else f"Class {cid}"
        detections.append(
            {
                "class_id": cid,
                "class_name": cname,
                "confidence": round(float(score), 6),
                "bbox_xyxy": [round(x1, 2), round(y1, 2), round(x2, 2), round(y2, 2)],
            }
        )
    return detections


def merge_per_leaf_yolo_detections(
    full_dets: list[dict[str, object]],
    tile_dets: list[dict[str, object]],
    rgb: np.ndarray,
) -> list[dict[str, object]]:
    """Prefer per-leaf YOLO boxes: use tile sub-boxes when full-frame missed leaves or merged a multi-leaf sprig on a uniform bg."""
    full_clean = suppress_overlapping_detections(full_dets, iou_threshold=0.45)
    tile_clean = suppress_overlapping_detections(tile_dets, iou_threshold=0.45)

    if len(tile_clean) >= 2 and len(tile_clean) > len(full_clean) and (not full_clean or analyze_uniform_or_screen_leaf(rgb) is not None):
        kept_full: list[dict[str, object]] = []
        for fd in full_clean:
            fbox = fd.get("bbox_xyxy")
            if not isinstance(fbox, list):
                continue
            fx1, fy1, fx2, fy2 = fbox
            fa = max(1.0, (fx2 - fx1) * (fy2 - fy1))
            contained = 0
            for td in tile_clean:
                tbox = td.get("bbox_xyxy")
                if not isinstance(tbox, list):
                    continue
                tx1, ty1, tx2, ty2 = tbox
                ta = max(1.0, (tx2 - tx1) * (ty2 - ty1))
                ix = max(0.0, min(fx2, tx2) - max(fx1, tx1)) * max(0.0, min(fy2, ty2) - max(fy1, ty1))
                if ix / ta >= 0.65 and ta < fa * 0.70:
                    contained += 1
            if contained < 2:
                kept_full.append(fd)
        merged = suppress_overlapping_detections(tile_clean + kept_full, iou_threshold=0.45)
        if merged:
            return merged

    if full_clean:
        return full_clean
    return tile_clean


def run_onnx_yolo(
    onnx_path: Path,
    names: list[str],
    rgb_np: np.ndarray,
    conf_threshold: float,
    fast_mode: bool = False,
) -> list[dict[str, object]]:
    orig_h, orig_w = rgb_np.shape[:2]
    session, input_name = _get_yolo_ort_session(onnx_path)

    full_dets = [
        d
        for d in _infer_onnx_crop(session, input_name, names, rgb_np, conf_threshold, 0, 0, orig_w, orig_h)
        if isinstance(d.get("bbox_xyxy"), list)
        and not is_non_leaf_class(str(d.get("class_name", "")))
        and is_valid_chili_leaf_box(
            rgb_np,
            d["bbox_xyxy"],  # type: ignore[arg-type]
            str(d.get("class_name", "")),
            float(d.get("confidence", 0.0)),
        )
    ]

    if fast_mode and full_dets:
        return suppress_overlapping_detections(full_dets, iou_threshold=0.45)

    tile_dets: list[dict[str, object]] = []
    if not fast_mode and orig_w >= 320 and orig_h >= 320:
        half_w, half_h = orig_w // 2, orig_h // 2
        pad_x, pad_y = int(orig_w * 0.08), int(orig_h * 0.08)
        for tx1, ty1, tx2, ty2 in [
            (0, 0, min(orig_w, half_w + pad_x), min(orig_h, half_h + pad_y)),
            (max(0, half_w - pad_x), 0, orig_w, min(orig_h, half_h + pad_y)),
            (0, max(0, half_h - pad_y), min(orig_w, half_w + pad_x), orig_h),
            (max(0, half_w - pad_x), max(0, half_h - pad_y), orig_w, orig_h),
        ]:
            tile_dets.extend(
                d
                for d in _infer_onnx_crop(
                    session, input_name, names, rgb_np[ty1:ty2, tx1:tx2], conf_threshold, tx1, ty1, orig_w, orig_h
                )
                if isinstance(d.get("bbox_xyxy"), list)
                and not is_non_leaf_class(str(d.get("class_name", "")))
                and is_valid_chili_leaf_box(
                    rgb_np,
                    d["bbox_xyxy"],  # type: ignore[arg-type]
                    str(d.get("class_name", "")),
                    float(d.get("confidence", 0.0)),
                )
            )

    if not full_dets:
        white_bg_dets = detect_white_bg_yolo_boxes(rgb_np, names, conf_threshold)
        if white_bg_dets:
            return white_bg_dets

    detections = merge_per_leaf_yolo_detections(full_dets, tile_dets, rgb_np)
    if detections:
        return detections

    white_bg_dets = detect_white_bg_yolo_boxes(rgb_np, names, conf_threshold)
    if white_bg_dets is not None:
        return white_bg_dets

    contour_dets, _ = detect_foliage_contour_fallback(rgb_np, names, conf_threshold)
    return suppress_overlapping_detections(contour_dets, iou_threshold=0.45)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", type=Path, required=True)
    parser.add_argument("--model-id", type=str, default="yolov8n-chili")
    parser.add_argument("--threshold", type=float, default=0.45)
    args = parser.parse_args()

    definition = MODEL_DEFINITIONS.get(args.model_id)
    if definition is None:
        print(json.dumps({"error": f"Model '{args.model_id}' tidak terdaftar."}))
        return 1

    model_path: Path = definition["path"]  # type: ignore[assignment]
    if not model_path.is_file():
        print(json.dumps({"error": f"Model belum tersedia: {model_path.name}"}))
        return 1

    if not args.image.is_file():
        print(json.dumps({"error": "File gambar tidak ditemukan."}))
        return 1

    try:
        image = Image.open(args.image).convert("RGB")
    except Exception as exc:
        print(json.dumps({"error": f"File bukan gambar valid: {exc}"}))
        return 1

    rgb_np = np.asarray(image)

    names_path: Path = definition["names_path"]  # type: ignore[assignment]
    if names_path.is_file():
        names = [format_class_name(str(item)) for item in json.loads(names_path.read_text(encoding="utf-8"))]
    else:
        names = list(DEFAULT_CLASS_NAMES)

    if definition["kind"] == "classification_onnx":
        import onnxruntime as ort

        session = ort.InferenceSession(str(model_path), providers=["CPUExecutionProvider"])
        input_name = session.get_inputs()[0].name

        # Model CNN 5-Channel dilatih per-daun (crop bbox + margin 8%).
        # Cek terlebih dahulu apakah foto diambil di atas latar seragam (putih/abu/gelap/studio).
        probabilities = None
        uniform_res = analyze_uniform_or_screen_leaf(rgb_np, size=128)
        if uniform_res is not None:
            st, _, big_spots, is_screen = uniform_res
            raw_probs = session.run(None, {input_name: st})[0][0]
            probabilities = adjust_uniform_bg_leaf_probs(raw_probs, big_spots, names, is_screen=is_screen)

        yolo_def = MODEL_DEFINITIONS.get("yolov8n-chili")
        yolo_onnx_path: Path | None = yolo_def["path"] if yolo_def else None  # type: ignore[assignment]
        if probabilities is None and yolo_onnx_path is not None and yolo_onnx_path.is_file() and min(rgb_np.shape[:2]) >= 32:
            try:
                yolo_sess = ort.InferenceSession(str(yolo_onnx_path), providers=["CPUExecutionProvider"])
                yolo_in = yolo_sess.get_inputs()[0].name
                h, w = rgb_np.shape[:2]
                leaf_dets = [
                    d
                    for d in _infer_onnx_crop(yolo_sess, yolo_in, names[:6], rgb_np, 0.20, 0, 0, w, h)
                    if isinstance(d.get("bbox_xyxy"), list)
                    and is_valid_chili_leaf_box(rgb_np, d["bbox_xyxy"])  # type: ignore[arg-type]
                ]
                leaf_dets = suppress_overlapping_detections(leaf_dets, iou_threshold=0.45)
                if leaf_dets:
                    top_dets = sorted(leaf_dets, key=lambda d: float(d["confidence"]), reverse=True)[:3]
                    valid_leaf_preds: list[tuple[np.ndarray, float]] = []
                    fallback_probs: np.ndarray | None = None
                    for d in top_dets:
                        box = d["bbox_xyxy"]
                        if isinstance(box, list) and (box[2] - box[0]) >= 12 and (box[3] - box[1]) >= 12:
                            roi_0 = preprocess_cnn_5ch(crop_leaf_roi_with_padding(rgb_np, box, pad_ratio=0.0), size=128)[0]
                            roi_8 = preprocess_cnn_5ch(crop_leaf_roi_with_padding(rgb_np, box, pad_ratio=0.08), size=128)[0]
                            outs = session.run(None, {input_name: np.stack([roi_0, roi_8], axis=0)})[0]
                            p = 0.35 * outs[0] + 0.65 * outs[1]
                            if fallback_probs is None:
                                fallback_probs = p
                            cid = int(np.argmax(p))
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
                probabilities = session.run(None, {input_name: input_tensor})[0][0]
            else:
                probabilities = np.zeros(len(names), dtype=np.float32)
                probabilities[-1] = 1.0

        conf_threshold = float(np.clip(args.threshold, 0.10, 0.95))
        class_id = int(np.argmax(probabilities))
        if is_non_leaf_class(names[class_id] if 0 <= class_id < len(names) else "") or float(probabilities[class_id]) < conf_threshold:
            _, contour_probs = detect_foliage_contour_fallback(
                rgb_np,
                names,
                conf_threshold,
                predict_batch_fn=lambda batch: session.run(None, {input_name: batch})[0],
            )
            if contour_probs is not None:
                probabilities = contour_probs

        class_id = int(np.argmax(probabilities))
        score = round(float(probabilities[class_id]), 6)
        class_name = names[class_id] if 0 <= class_id < len(names) else f"Class {class_id}"
        if score >= conf_threshold and not is_non_leaf_class(class_name):
            classification: dict[str, object] | None = {
                "class_id": class_id,
                "class_name": class_name,
                "confidence": score,
            }
            count = 1
        else:
            classification = None
            count = 0
        payload = {
            "model_id": args.model_id,
            "model": model_path.name,
            "threshold": conf_threshold,
            "best_candidate_confidence": 0.0 if is_non_leaf_class(class_name) else score,
            "image_width": image.width,
            "image_height": image.height,
            "count": count,
            "classification": classification,
            "primary_detection": classification,
            "detections": [],
        }
        print(json.dumps(payload))
        return 0

    yolo_threshold = float(np.clip(args.threshold, 0.10, 0.95))
    detections = run_onnx_yolo(model_path, names, rgb_np, yolo_threshold)
    primary = select_primary_detection(detections)
    payload = {
        "model_id": args.model_id,
        "model": model_path.name,
        "threshold": yolo_threshold,
        "image_width": image.width,
        "image_height": image.height,
        "count": len(detections),
        "classification": None,
        "primary_detection": primary,
        "detections": detections,
    }
    print(json.dumps(payload))
    return 0


if __name__ == "__main__":
    sys.exit(main())
