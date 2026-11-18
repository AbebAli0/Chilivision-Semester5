"""Tkinter webcam classifier with conservative low-confidence messaging."""
from __future__ import annotations

import argparse
import json
import os
import time
from pathlib import Path
import tkinter as tk
from tkinter import messagebox, ttk

import cv2
import numpy as np
import tensorflow as tf
from PIL import Image, ImageTk


class WebcamApp:
    def __init__(self, model_path: Path, classes_path: Path, camera_index: int, threshold: float) -> None:
        self.root = tk.Tk()
        self.root.title("Monitoring Daun Cabai - MobileNetV2")
        self.video = ttk.Label(self.root)
        self.video.pack(padx=8, pady=8)
        self.status = ttk.Label(self.root, text="Kamera belum dibuka")
        self.status.pack()
        controls = ttk.Frame(self.root); controls.pack(pady=8)
        ttk.Button(controls, text="Buka kamera", command=self.open_camera).pack(side=tk.LEFT, padx=4)
        ttk.Button(controls, text="Hentikan", command=self.stop_camera).pack(side=tk.LEFT, padx=4)
        ttk.Button(controls, text="Keluar", command=self.close).pack(side=tk.LEFT, padx=4)
        self.model = tf.keras.models.load_model(model_path)
        self.classes = json.loads(classes_path.read_text(encoding="utf-8"))
        self.camera_index = camera_index
        self.threshold = threshold
        self.capture: cv2.VideoCapture | None = None
        self.running = False
        self.last_inference = 0.0

    def open_camera(self) -> None:
        self.stop_camera()
        self.capture = cv2.VideoCapture(self.camera_index, cv2.CAP_DSHOW)
        if not self.capture.isOpened():
            self.capture.release(); self.capture = None
            messagebox.showerror("Kamera", f"Webcam indeks {self.camera_index} tidak dapat dibuka.")
            return
        self.running = True
        self.status.configure(text=f"Kamera terhubung (indeks {self.camera_index})")
        self.refresh()

    def refresh(self) -> None:
        if not self.running or self.capture is None:
            return
        ok, frame = self.capture.read()
        if not ok:
            self.status.configure(text="Gagal membaca frame kamera")
            self.root.after(200, self.refresh); return
        now = time.monotonic()
        if now - self.last_inference >= 0.5:
            self.last_inference = now
            rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            batch = np.expand_dims(cv2.resize(rgb, (224, 224)), axis=0)
            probabilities = self.model.predict(batch, verbose=0)[0]
            top = probabilities.argsort()[-3:][::-1]
            confidence = float(probabilities[top[0]])
            label = str(self.classes[top[0]]) if confidence >= self.threshold else "Perlu pemeriksaan ulang"
            details = ", ".join(f"{self.classes[i]} ({probabilities[i] * 100:.1f}%)" for i in top)
            self.status.configure(text=f"{label} | Top-3: {details}")
        display = Image.fromarray(cv2.cvtColor(frame, cv2.COLOR_BGR2RGB))
        display.thumbnail((800, 600))
        photo = ImageTk.PhotoImage(display)
        self.video.configure(image=photo); self.video.image = photo
        self.root.after(30, self.refresh)

    def stop_camera(self) -> None:
        self.running = False
        if self.capture is not None:
            self.capture.release(); self.capture = None
        self.status.configure(text="Kamera dihentikan")

    def close(self) -> None:
        self.stop_camera(); self.root.destroy()


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", type=Path, default=Path("models/mobilenetv2_cabai.keras"))
    parser.add_argument("--classes", type=Path, default=Path("models/class_names.json"))
    parser.add_argument("--camera", type=int, default=int(os.getenv("CAMERA_INDEX", "0")))
    parser.add_argument("--threshold", type=float, default=float(os.getenv("CONFIDENCE_THRESHOLD", "0.70")))
    args = parser.parse_args()
    if not args.model.exists() or not args.classes.exists():
        raise FileNotFoundError("Model atau class_names.json tidak ditemukan. Jalankan training terlebih dahulu.")
    app = WebcamApp(args.model, args.classes, args.camera, args.threshold)
    app.root.protocol("WM_DELETE_WINDOW", app.close)
    app.root.mainloop()


if __name__ == "__main__":
    main()
