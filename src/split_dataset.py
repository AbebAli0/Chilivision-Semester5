"""Validate and split a class-folder image dataset into train/val/test."""
from __future__ import annotations

import argparse
import csv
import hashlib
import os
import random
import shutil
from pathlib import Path

from dotenv import load_dotenv
from PIL import Image, UnidentifiedImageError
from sklearn.model_selection import train_test_split

SEED = 42
SPLITS = ("train", "val", "test")


def valid_image(path: Path) -> bool:
    try:
        with Image.open(path) as image:
            image.verify()
        return True
    except (OSError, UnidentifiedImageError):
        return False


def file_hash(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def split_dataset(source: Path, destination: Path) -> tuple[int, int]:
    random.seed(SEED)
    if (source / "train").is_dir():
        source = source / "train"
        print(f"Dataset terpisah terdeteksi; memakai folder train: {source}")
    destination.mkdir(parents=True, exist_ok=True)
    for split in SPLITS:
        shutil.rmtree(destination / split, ignore_errors=True)
    bad_files: list[tuple[str, str]] = []
    seen_hashes: set[str] = set()
    copied = 0
    classes = sorted(path for path in source.iterdir() if path.is_dir())
    if not classes:
        raise FileNotFoundError(f"Tidak ada folder kelas di {source}")

    for class_dir in classes:
        files = sorted(path for path in class_dir.rglob("*") if path.is_file())
        good = []
        for path in files:
            if valid_image(path):
                digest = file_hash(path)
                if digest in seen_hashes:
                    bad_files.append((str(path), "duplikat persis dari gambar lain"))
                else:
                    seen_hashes.add(digest)
                    good.append(path)
            else:
                bad_files.append((str(path), "gambar rusak/tidak didukung"))
        if len(good) < 3:
            raise ValueError(f"Kelas '{class_dir.name}' membutuhkan sedikitnya 3 gambar valid.")
        train, remainder = train_test_split(good, test_size=0.30, random_state=SEED)
        val, test = train_test_split(remainder, test_size=0.50, random_state=SEED)
        for split, paths in zip(SPLITS, (train, val, test)):
            target = destination / split / class_dir.name
            target.mkdir(parents=True, exist_ok=True)
            for index, path in enumerate(paths):
                target_name = f"{path.stem}_{index}{path.suffix.lower()}"
                shutil.copy2(path, target / target_name)
                copied += 1

    with (destination / "invalid_images.csv").open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(("file", "reason"))
        writer.writerows(bad_files)
    print(f"Selesai: {copied} gambar disalin; {len(bad_files)} gambar diabaikan.")
    return copied, len(bad_files)


def main() -> None:
    load_dotenv()
    parser = argparse.ArgumentParser()
    default_source = os.getenv("DATASET_SOURCE", "dataset_raw")
    parser.add_argument("--source", type=Path, default=Path(default_source))
    parser.add_argument("--destination", type=Path, default=Path("dataset_split"))
    args = parser.parse_args()
    split_dataset(args.source, args.destination)


if __name__ == "__main__":
    main()
