"""Prepare a classification-folder dataset for detector training.

The source images do not contain object coordinates. This tool therefore
creates full-image boxes as a temporary bootstrap dataset and reports that
limitation explicitly. For production accuracy, replace these labels with
manually drawn leaf/chili boxes and add negative background images.
"""
from __future__ import annotations

import argparse
import random
import shutil
from pathlib import Path

IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp"}
CLASS_NAMES = [
    "Penyakit Bercak Daun",
    "Daun Sehat",
    "Kekurangan Nutrisi",
    "Hama Kutu Kebul",
    "Penyakit Antraknosa",
    "Penyakit Daun Keriting",
    "Penyakit Virus Kuning",
]


def collect_images(source: Path) -> dict[str, list[Path]]:
    classes = {name: [] for name in CLASS_NAMES}
    for class_name in CLASS_NAMES:
        class_dir = source / class_name
        if not class_dir.is_dir():
            raise FileNotFoundError(f"Folder kelas tidak ditemukan: {class_dir}")
        classes[class_name] = sorted(
            image
            for image in class_dir.iterdir()
            if image.is_file() and image.suffix.lower() in IMAGE_EXTENSIONS
        )
        if not classes[class_name]:
            raise ValueError(f"Tidak ada gambar pada folder: {class_dir}")
    return classes


def prepare(source: Path, output: Path, seed: int = 42) -> None:
    classes = collect_images(source)
    rng = random.Random(seed)
    for split in ("train", "valid", "test"):
        (output / split / "images").mkdir(parents=True, exist_ok=True)
        (output / split / "labels").mkdir(parents=True, exist_ok=True)

    totals = [0, 0, 0]
    for class_id, class_name in enumerate(CLASS_NAMES):
        images = classes[class_name][:]
        rng.shuffle(images)
        n = len(images)
        boundaries = (round(n * 0.70), round(n * 0.85))
        for index, image in enumerate(images):
            split = "train" if index < boundaries[0] else "valid" if index < boundaries[1] else "test"
            destination_stem = f"{class_id}_{image.stem}_{index}"
            destination = output / split / "images" / f"{destination_stem}{image.suffix.lower()}"
            shutil.copy2(image, destination)
            # Bootstrap annotation: the source has no coordinates.
            (output / split / "labels" / f"{destination_stem}.txt").write_text(
                f"{class_id} 0.5 0.5 1.0 1.0\n", encoding="utf-8"
            )
            totals[("train", "valid", "test").index(split)] += 1

    print(f"Prepared {sum(totals)} images from {source}")
    print(f"Splits: train={totals[0]}, valid={totals[1]}, test={totals[2]}")
    print("WARNING: generated boxes cover the entire image because source labels have no coordinates.")
    print("Draw leaf/chili boxes and add negative background images before judging camera accuracy.")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()
    prepare(args.source.resolve(), args.output.resolve(), args.seed)


if __name__ == "__main__":
    main()
