#!/usr/bin/env python3
"""Build the case-study images from the raw captures in docs/scratch/.

portfolio/assets/<name>.jpg       1000 px wide, what the page shows
portfolio/assets/full/<name>.jpg  1568 px wide, what "enlarge" loads

Run bin/capture-screens.sh first.
"""
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
RAW = ROOT / "docs" / "scratch"
OUT = ROOT / "portfolio" / "assets"
PAPER = (249, 248, 244)  # the theme's own page ground

DESKTOP = {"01-home": "home", "02-services": "services", "03-about": "about"}


def save_pair(img: Image.Image, name: str) -> None:
    (OUT / "full").mkdir(parents=True, exist_ok=True)
    full = img.convert("RGB")
    full.save(OUT / "full" / f"{name}.jpg", quality=86, optimize=True, progressive=True)
    small = full.resize((1000, round(full.height * 1000 / full.width)), Image.LANCZOS)
    small.save(OUT / f"{name}.jpg", quality=84, optimize=True, progressive=True)
    print(f"{name}: {full.size} + {small.size}")


def phones_strip() -> Image.Image:
    # phones.png holds three 390px frames side by side (see capture-screens.sh);
    # fit it onto the same 1568x743 plate as the desktop shots.
    shot = Image.open(RAW / "phones.png").convert("RGB")
    canvas = Image.new("RGB", (1568, 743), PAPER)
    height = 743
    width = round(shot.width * height / shot.height)
    shot = shot.resize((width, height), Image.LANCZOS)
    canvas.paste(shot, ((canvas.width - width) // 2, 0))
    return canvas


def main() -> None:
    for name, raw in DESKTOP.items():
        save_pair(Image.open(RAW / f"{raw}.png"), name)
    save_pair(phones_strip(), "04-mobile")


if __name__ == "__main__":
    main()
