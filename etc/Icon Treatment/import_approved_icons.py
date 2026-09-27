#!/usr/bin/env python3
"""Publica grupos de ícones PNG aprovados no catálogo versionado da aplicação."""

from __future__ import annotations

import argparse
import re
import sys
from collections import defaultdict
from pathlib import Path

try:
    from PIL import Image, UnidentifiedImageError
except ImportError as error:
    raise SystemExit(
        "Pillow não está instalado. Execute: py -3 -m pip install Pillow"
    ) from error


SIZES = (512, 48, 32, 24)
FILE_PATTERN = re.compile(r"^(?P<name>.+)_(?P<size>512|48|32|24)\.png$", re.IGNORECASE)
SCRIPT_DIRECTORY = Path(__file__).resolve().parent
OUTPUT_DIRECTORY = SCRIPT_DIRECTORY / "output"
CATALOG_DIRECTORY = SCRIPT_DIRECTORY.parents[1] / "public" / "assets" / "icons"


def approved_groups() -> dict[str, dict[int, Path]]:
    """Agrupa os PNGs candidatos por nome, sem impedir conjuntos independentes."""
    groups: dict[str, dict[int, Path]] = defaultdict(dict)
    for candidate in OUTPUT_DIRECTORY.iterdir() if OUTPUT_DIRECTORY.exists() else ():
        if not candidate.is_file():
            continue

        match = FILE_PATTERN.fullmatch(candidate.name)
        if match is None:
            continue

        groups[match.group("name")][int(match.group("size"))] = candidate

    if not groups:
        raise ValueError("nenhum ícone aprovado foi encontrado em output")

    return dict(groups)


def group_validation_error(name: str, variants: dict[int, Path]) -> str | None:
    """Retorna a razão para não publicar um grupo sem afetar os demais grupos."""
    missing = sorted(set(SIZES) - set(variants))
    if missing:
        return f"conjunto {name} incompleto; faltam as variantes " + ", ".join(map(str, missing))

    for size in SIZES:
        source = variants[size]
        try:
            with Image.open(source) as image:
                if image.format != "PNG" or image.size != (size, size):
                    return (
                        f"{source.name}: esperado PNG {size}x{size}; "
                        f"recebido {image.format} {image.width}x{image.height}"
                    )
        except UnidentifiedImageError:
            return f"{source.name}: arquivo de imagem inválido"

    return None


def publish(
    groups: dict[str, dict[int, Path]], replace: bool, dry_run: bool
) -> tuple[list[tuple[str, Path, Path, str | None]], bool]:
    """Processa cada conjunto de forma independente e retorna seus resultados."""
    CATALOG_DIRECTORY.mkdir(parents=True, exist_ok=True)
    results: list[tuple[str, Path, Path, str | None]] = []
    failed = False

    for name in sorted(groups):
        variants = groups[name]
        sources = [variants[size] for size in SIZES if size in variants]
        targets = [(source, CATALOG_DIRECTORY / source.name) for source in sources]
        validation_error = group_validation_error(name, variants)
        if validation_error:
            failed = True
            results.extend(("ERRO", source, target, validation_error) for source, target in targets)
            continue

        existing = [target.name for _, target in targets if target.exists()]
        if existing and not replace:
            for source, target in targets:
                message = (
                    "já publicado"
                    if target.exists()
                    else "não movido; outro arquivo do mesmo conjunto já está publicado"
                )
                results.append(("IGNORADO", source, target, message))
            continue

        if dry_run:
            results.extend(("VALIDADO", source, target, None) for source, target in targets)
            continue

        for source, target in targets:
            try:
                source.replace(target)
            except OSError as error:
                failed = True
                results.append(("ERRO", source, target, str(error)))
            else:
                results.append(("PUBLICADO", source, target, None))

    return results, failed


def main(arguments: list[str]) -> int:
    parser = argparse.ArgumentParser(
        description="Move os ícones aprovados de output para public/assets/icons."
    )
    parser.add_argument(
        "--replace",
        action="store_true",
        help="permite substituir ícones já publicados",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="valida e lista os destinos sem copiar arquivos",
    )
    options = parser.parse_args(arguments)

    try:
        results, failed = publish(approved_groups(), options.replace, options.dry_run)
    except (OSError, ValueError) as error:
        print(f"ERRO: {error}", file=sys.stderr)
        return 1

    for action, source, target, message in results:
        suffix = f" ({message})" if message else ""
        print(f"{action}: {source.name} -> {target}{suffix}")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
