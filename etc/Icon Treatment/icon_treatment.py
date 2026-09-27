#!/usr/bin/env python3
"""Normaliza ícones raster e cria as dimensões consumidas pela aplicação."""

from __future__ import annotations

import sys
from pathlib import Path

try:
    from PIL import Image, ImageOps, UnidentifiedImageError
except ImportError as error:
    raise SystemExit(
        "Pillow não está instalado. Execute: py -3 -m pip install Pillow"
    ) from error


SIZES = (512, 48, 32, 24)
SCRIPT_DIRECTORY = Path(__file__).resolve().parent
OUTPUT_DIRECTORY = SCRIPT_DIRECTORY / "output"


def normalized_canvas(source_path: Path) -> Image.Image:
    """Remove a borda inteiramente transparente e centraliza o conteúdo em um quadrado."""
    with Image.open(source_path) as source:
        image = ImageOps.exif_transpose(source).convert("RGBA")

    content_bounds = image.getchannel("A").getbbox()
    if content_bounds is None:
        raise ValueError("a imagem não possui pixels visíveis")

    cropped = image.crop(content_bounds)
    side = max(cropped.size)
    canvas = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    offset = ((side - cropped.width) // 2, (side - cropped.height) // 2)
    canvas.alpha_composite(cropped, offset)
    return canvas


def save_sizes(source_path: Path) -> list[Path]:
    """Gera versões quadradas em PNG preservando transparência e o nome-base recebido."""
    source = normalized_canvas(source_path)
    OUTPUT_DIRECTORY.mkdir(parents=True, exist_ok=True)
    generated: list[Path] = []

    for size in SIZES:
        target = OUTPUT_DIRECTORY / f"{source_path.stem}_{size}.png"
        resized = source.resize((size, size), Image.Resampling.LANCZOS)
        resized.save(target, format="PNG", optimize=True)
        generated.append(target)

    return generated


def main(arguments: list[str]) -> int:
    if not arguments:
        print("Uso: py -3 icon_treatment.py <imagem> [imagem ...]", file=sys.stderr)
        return 1

    failures = 0
    for argument in arguments:
        source_path = Path(argument)
        if not source_path.is_file():
            print(f"ERRO: arquivo não encontrado: {source_path}", file=sys.stderr)
            failures += 1
            continue

        try:
            generated = save_sizes(source_path)
        except (OSError, UnidentifiedImageError, ValueError) as error:
            print(f"ERRO: {source_path.name}: {error}", file=sys.stderr)
            failures += 1
            continue

        for target in generated:
            print(target)

    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
