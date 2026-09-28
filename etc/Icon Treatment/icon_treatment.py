#!/usr/bin/env python3
"""Normaliza ícones raster e cria as dimensões consumidas pela aplicação."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    from PIL import Image, ImageOps, UnidentifiedImageError
except ImportError as error:
    raise SystemExit(
        "Pillow não está instalado. Execute: py -3 -m pip install Pillow"
    ) from error


SIZES = (512, 48, 32, 24)
DEFAULT_ALPHA_TRIM_THRESHOLD = 16
SCRIPT_DIRECTORY = Path(__file__).resolve().parent
OUTPUT_DIRECTORY = SCRIPT_DIRECTORY / "output"


def normalized_canvas(
    source_path: Path, alpha_trim_threshold: int = DEFAULT_ALPHA_TRIM_THRESHOLD
) -> Image.Image:
    """Remove ruído translúcido de borda e centraliza o conteúdo visível em um quadrado."""
    with Image.open(source_path) as source:
        image = ImageOps.exif_transpose(source).convert("RGBA")

    alpha = image.getchannel("A")
    visible_alpha = alpha.point(
        lambda value: 255 if value >= alpha_trim_threshold else 0
    )
    content_bounds = visible_alpha.getbbox()
    if content_bounds is None:
        raise ValueError(
            "a imagem não possui pixels visíveis acima do limiar de transparência"
        )

    cropped = image.crop(content_bounds)
    side = max(cropped.size)
    canvas = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    offset = ((side - cropped.width) // 2, (side - cropped.height) // 2)
    canvas.alpha_composite(cropped, offset)
    return canvas


def save_sizes(source_path: Path, alpha_trim_threshold: int) -> list[Path]:
    """Gera versões quadradas em PNG preservando transparência e o nome-base recebido."""
    source = normalized_canvas(source_path, alpha_trim_threshold)
    OUTPUT_DIRECTORY.mkdir(parents=True, exist_ok=True)
    generated: list[Path] = []

    for size in SIZES:
        target = OUTPUT_DIRECTORY / f"{source_path.stem}_{size}.png"
        resized = source.resize((size, size), Image.Resampling.LANCZOS)
        resized.save(target, format="PNG", optimize=True)
        generated.append(target)

    return generated


def main(arguments: list[str]) -> int:
    parser = argparse.ArgumentParser(
        description="Remove bordas transparentes e gera variantes PNG para a aplicação."
    )
    parser.add_argument(
        "--alpha-trim-threshold",
        type=int,
        default=DEFAULT_ALPHA_TRIM_THRESHOLD,
        help=(
            "alfa mínimo (1-255) considerado conteúdo; o padrão 16 ignora "
            "pixels residuais quase transparentes"
        ),
    )
    parser.add_argument("images", metavar="imagem", nargs="+")
    options = parser.parse_args(arguments)
    if not 1 <= options.alpha_trim_threshold <= 255:
        parser.error("--alpha-trim-threshold deve estar entre 1 e 255")

    failures = 0
    for argument in options.images:
        source_path = Path(argument)
        if not source_path.is_file():
            print(f"ERRO: arquivo não encontrado: {source_path}", file=sys.stderr)
            failures += 1
            continue

        try:
            generated = save_sizes(source_path, options.alpha_trim_threshold)
        except (OSError, UnidentifiedImageError, ValueError) as error:
            print(f"ERRO: {source_path.name}: {error}", file=sys.stderr)
            failures += 1
            continue

        for target in generated:
            print(target)

    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
