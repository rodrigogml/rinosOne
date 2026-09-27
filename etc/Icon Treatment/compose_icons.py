#!/usr/bin/env python3
"""Compõe dois ícones raster em uma única imagem sem perder a transparência."""

from __future__ import annotations

import sys
from pathlib import Path

from PIL import Image

from icon_treatment import OUTPUT_DIRECTORY, SIZES, normalized_canvas


CANVAS_SIZE = 512
PRIMARY_SIZE = int(CANVAS_SIZE * 0.85)
SECONDARY_SIZE = int(CANVAS_SIZE * 0.50)
SIZE_SUFFIXES = tuple(f"_{size}" for size in SIZES)


def output_stem(source: Path) -> str:
    """Remove o sufixo de tamanho de um arquivo que já tenha sido normalizado."""
    for suffix in SIZE_SUFFIXES:
        if source.stem.endswith(suffix):
            return source.stem[: -len(suffix)]
    return source.stem


def choose_primary(first: Path, second: Path) -> tuple[Path, Path]:
    """Solicita qual arquivo ocupa a camada principal da composição."""
    print("Qual ícone deve ser o principal (camada inferior, canto superior esquerdo)?")
    print(f"1. {first.name}")
    print(f"2. {second.name}")

    while True:
        choice = input("Digite 1 ou 2: ").strip()
        if choice == "1":
            return first, second
        if choice == "2":
            return second, first
        print("Opção inválida. Digite 1 ou 2.")


def compose(primary_path: Path, secondary_path: Path) -> Image.Image:
    """Sobrepõe 85% e 50% dos ícones em uma área final de 512 por 512 pixels."""
    primary = normalized_canvas(primary_path).resize(
        (PRIMARY_SIZE, PRIMARY_SIZE), Image.Resampling.LANCZOS
    )
    secondary = normalized_canvas(secondary_path).resize(
        (SECONDARY_SIZE, SECONDARY_SIZE), Image.Resampling.LANCZOS
    )
    composition = Image.new("RGBA", (CANVAS_SIZE, CANVAS_SIZE), (0, 0, 0, 0))
    composition.alpha_composite(primary, (0, 0))
    composition.alpha_composite(secondary, (CANVAS_SIZE - SECONDARY_SIZE, CANVAS_SIZE - SECONDARY_SIZE))
    return composition


def save_composition(primary: Path, secondary: Path) -> list[Path]:
    """Cria todas as variantes de uma composição a partir das duas imagens selecionadas."""
    composition = compose(primary, secondary)
    OUTPUT_DIRECTORY.mkdir(parents=True, exist_ok=True)
    stem = f"{output_stem(primary)}-{output_stem(secondary)}"
    generated: list[Path] = []

    for size in SIZES:
        target = OUTPUT_DIRECTORY / f"{stem}_{size}.png"
        composition.resize((size, size), Image.Resampling.LANCZOS).save(
            target, format="PNG", optimize=True
        )
        generated.append(target)

    return generated


def main(arguments: list[str]) -> int:
    if len(arguments) != 2:
        print("Uso: py -3 compose_icons.py <ícone-1> <ícone-2>", file=sys.stderr)
        return 1

    candidates = tuple(Path(argument) for argument in arguments)
    missing = [str(candidate) for candidate in candidates if not candidate.is_file()]
    if missing:
        print("ERRO: arquivo não encontrado: " + ", ".join(missing), file=sys.stderr)
        return 1

    try:
        primary, secondary = choose_primary(*candidates)
        generated = save_composition(primary, secondary)
    except (EOFError, OSError, ValueError) as error:
        print(f"ERRO: {error}", file=sys.stderr)
        return 1

    for target in generated:
        print(f"GERADO: {target}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
