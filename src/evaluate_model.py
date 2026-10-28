"""Evaluate the saved model once on the held-out test split."""
from __future__ import annotations

import argparse
import json
from pathlib import Path

import matplotlib.pyplot as plt
import numpy as np
import tensorflow as tf
from sklearn.metrics import ConfusionMatrixDisplay, classification_report, confusion_matrix


def evaluate(data_dir: Path, model_path: Path, class_names_path: Path, reports_dir: Path) -> None:
    reports_dir.mkdir(parents=True, exist_ok=True)
    names = json.loads(class_names_path.read_text(encoding="utf-8"))
    ds = tf.keras.utils.image_dataset_from_directory(
        data_dir / "test", image_size=(224, 224), batch_size=32, shuffle=False,
        class_names=names,
    )
    model = tf.keras.models.load_model(model_path)
    loss, accuracy = model.evaluate(ds, verbose=0)
    probabilities = model.predict(ds, verbose=0)
    y_true = np.concatenate([labels.numpy() for _, labels in ds])
    y_pred = probabilities.argmax(axis=1)
    report = classification_report(y_true, y_pred, target_names=names, output_dict=True, zero_division=0)
    payload = {"loss": float(loss), "accuracy": float(accuracy), "classification_report": report}
    (reports_dir / "evaluation.json").write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    matrix = confusion_matrix(y_true, y_pred)
    ConfusionMatrixDisplay(matrix, display_labels=names).plot(xticks_rotation=45, cmap="Blues")
    plt.tight_layout(); plt.savefig(reports_dir / "confusion_matrix.png", dpi=150); plt.close()
    print(json.dumps(payload, ensure_ascii=False, indent=2))


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", type=Path, default=Path("dataset_split"))
    parser.add_argument("--model", type=Path, default=Path("models/mobilenetv2_cabai.keras"))
    parser.add_argument("--classes", type=Path, default=Path("models/class_names.json"))
    parser.add_argument("--reports", type=Path, default=Path("reports"))
    args = parser.parse_args()
    evaluate(args.data, args.model, args.classes, args.reports)


if __name__ == "__main__":
    main()
