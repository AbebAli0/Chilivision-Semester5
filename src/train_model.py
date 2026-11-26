"""Train MobileNetV2 transfer-learning model from dataset_split/train and val."""
from __future__ import annotations

import argparse
import json
from pathlib import Path

import matplotlib.pyplot as plt
import tensorflow as tf

IMAGE_SIZE = (224, 224)
SEED = 42


def train(
    data_dir: Path,
    model_dir: Path,
    reports_dir: Path,
    epochs: int,
    fine_tune_epochs: int,
    batch_size: int,
) -> Path:
    tf.random.set_seed(SEED)
    model_dir.mkdir(parents=True, exist_ok=True)
    reports_dir.mkdir(parents=True, exist_ok=True)
    train_ds = tf.keras.utils.image_dataset_from_directory(
        data_dir / "train", image_size=IMAGE_SIZE, batch_size=batch_size, seed=SEED,
    )
    val_ds = tf.keras.utils.image_dataset_from_directory(
        data_dir / "val", image_size=IMAGE_SIZE, batch_size=batch_size, shuffle=False,
    )
    class_names = train_ds.class_names
    if len(class_names) != 8:
        raise ValueError(f"Diharapkan 8 kelas, ditemukan {len(class_names)}: {class_names}")
    (model_dir / "class_names.json").write_text(
        json.dumps(class_names, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    autotune = tf.data.AUTOTUNE
    augmentation = tf.keras.Sequential([
        tf.keras.layers.RandomFlip("horizontal"),
        tf.keras.layers.RandomRotation(0.08),
        tf.keras.layers.RandomZoom(0.15),
    ])
    base = tf.keras.applications.MobileNetV2(
        input_shape=(*IMAGE_SIZE, 3), include_top=False, weights="imagenet"
    )
    base.trainable = False
    inputs = tf.keras.Input(shape=(*IMAGE_SIZE, 3))
    x = augmentation(inputs)
    x = tf.keras.applications.mobilenet_v2.preprocess_input(x)
    x = base(x, training=False)
    x = tf.keras.layers.GlobalAveragePooling2D()(x)
    x = tf.keras.layers.Dropout(0.25)(x)
    outputs = tf.keras.layers.Dense(len(class_names), activation="softmax")(x)
    model = tf.keras.Model(inputs, outputs)
    model.compile(
        optimizer=tf.keras.optimizers.Adam(1e-3),
        loss="sparse_categorical_crossentropy",
        metrics=["accuracy"],
    )
    train_ds = train_ds.prefetch(autotune)
    val_ds = val_ds.prefetch(autotune)
    model_path = model_dir / "mobilenetv2_cabai.keras"
    callbacks = [
        tf.keras.callbacks.EarlyStopping(monitor="val_loss", patience=6, restore_best_weights=True),
        tf.keras.callbacks.ModelCheckpoint(model_path, monitor="val_accuracy", save_best_only=True),
    ]
    history = model.fit(train_ds, validation_data=val_ds, epochs=epochs, callbacks=callbacks)
    if fine_tune_epochs > 0:
        base.trainable = True
        for layer in base.layers[:-30]:
            layer.trainable = False
        model.compile(
            optimizer=tf.keras.optimizers.Adam(1e-5),
            loss="sparse_categorical_crossentropy",
            metrics=["accuracy"],
        )
        model.fit(
            train_ds,
            validation_data=val_ds,
            epochs=epochs + fine_tune_epochs,
            callbacks=callbacks,
            initial_epoch=epochs,
        )
    fig, axes = plt.subplots(1, 2, figsize=(12, 4))
    axes[0].plot(history.history["accuracy"], label="train")
    axes[0].plot(history.history["val_accuracy"], label="validation")
    axes[0].set_title("Accuracy"); axes[0].legend()
    axes[1].plot(history.history["loss"], label="train")
    axes[1].plot(history.history["val_loss"], label="validation")
    axes[1].set_title("Loss"); axes[1].legend()
    fig.tight_layout(); fig.savefig(reports_dir / "training_curves.png", dpi=150); plt.close(fig)
    return model_path


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", type=Path, default=Path("dataset_split"))
    parser.add_argument("--models", type=Path, default=Path("models"))
    parser.add_argument("--reports", type=Path, default=Path("reports"))
    parser.add_argument("--epochs", type=int, default=25)
    parser.add_argument(
        "--fine-tune-epochs",
        type=int,
        default=0,
        help="Opsional: buka 30 layer terakhir MobileNetV2 dengan learning rate kecil.",
    )
    parser.add_argument("--batch-size", type=int, default=64)
    args = parser.parse_args()
    train(args.data, args.models, args.reports, args.epochs, args.fine_tune_epochs, args.batch_size)


if __name__ == "__main__":
    main()
