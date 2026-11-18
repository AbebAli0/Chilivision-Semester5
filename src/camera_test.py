"""Capture one webcam frame and classify it with the project's Keras model."""
from __future__ import annotations

import argparse
import json
from pathlib import Path

import cv2
import numpy as np
import tensorflow as tf

PROJECT_ROOT = Path(__file__).resolve().parents[1]


def open_camera(index: int) -> cv2.VideoCapture:
    capture = cv2.VideoCapture(index, cv2.CAP_DSHOW)
    if not capture.isOpened():
        capture.release()
        capture = cv2.VideoCapture(index)
    if not capture.isOpened():
        raise RuntimeError(
            f"Kamera indeks {index} tidak dapat dibuka. "
            "Coba --camera 1 atau tutup aplikasi lain yang memakai kamera."
        )
    return capture


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--camera", type=int, default=0)
    parser.add_argument("--threshold", type=float, default=0.70)
    parser.add_argument("--output", type=Path, default=PROJECT_ROOT / "reports" / "camera_test.jpg")
    args = parser.parse_args()
    if not 0 <= args.threshold <= 1:
        raise ValueError("--threshold harus berada di antara 0 dan 1.")

    model = tf.keras.models.load_model(PROJECT_ROOT / "models" / "mobilenetv2_cabai.keras")
    classes = json.loads(
        (PROJECT_ROOT / "models" / "class_names.json").read_text(encoding="utf-8")
    )
    capture = open_camera(args.camera)
    try:
        ok, frame = capture.read()
    finally:
        capture.release()
    if not ok or frame is None:
        raise RuntimeError("Kamera terbuka tetapi frame tidak dapat dibaca.")

    rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    batch = np.expand_dims(cv2.resize(rgb, (224, 224)), axis=0)
    probabilities = model.predict(batch, verbose=0)[0]
    top_indices = probabilities.argsort()[-3:][::-1]
    confidence = float(probabilities[top_indices[0]])
    label = classes[top_indices[0]] if confidence >= args.threshold else "Perlu pemeriksaan ulang"

    args.output.parent.mkdir(parents=True, exist_ok=True)
    cv2.imwrite(str(args.output), frame)
    details = ", ".join(
        f"{classes[index]} ({probabilities[index] * 100:.1f}%)" for index in top_indices
    )
    print(f"Frame tersimpan: {args.output}")
    print(f"Hasil: {label} | Top-3: {details}")


if __name__ == "__main__":
    main()
