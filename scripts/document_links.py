"""Depo Markdown bağlantıları: yerel dosya/başlık ve dış URL biçimi."""
from pathlib import Path
import re
import unicodedata
from urllib.parse import unquote, urlsplit


def prose(text):
    """Örnek kod bloklarındaki bağlantı benzeri metni değerlendirme."""
    fence = None
    for line in text.splitlines():
        marker = re.match(r"^\s{0,3}(`{3,}|~{3,})", line)
        if marker:
            token = marker.group(1)
            if fence is None:
                fence = token
            elif token[0] == fence[0] and len(token) >= len(fence):
                fence = None
            continue
        if fence is None:
            yield line


def anchors(text):
    result, counts = set(), {}
    for line in prose(text):
        match = re.match(r"^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$", line)
        if not match:
            continue
        label = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", match.group(1))
        label = re.sub(r"<[^>]+>", "", label).lower()
        slug = "".join(c for c in label if c in " _-" or unicodedata.category(c)[0] in "LNM").replace(" ", "-")
        count = counts.get(slug, 0)
        counts[slug] = count + 1
        result.add(slug if count == 0 else f"{slug}-{count}")
    return result


def validate_documents(root, documents):
    root = Path(root).resolve()
    errors, checked = [], 0
    for md in documents:
        for line in prose(md.read_text(encoding="utf-8")):
            for value in re.findall(r"!?\[[^\]]*\]\(([^)]+)\)", line):
                checked += 1
                value = value.strip().removeprefix("<").removesuffix(">")
                try:
                    parsed = urlsplit(value)
                    if parsed.scheme in {"https", "http"}:
                        if not parsed.hostname or any(c.isspace() for c in value):
                            raise ValueError("dış URL biçimi geçersiz")
                        continue
                    if parsed.scheme == "mailto":
                        if "@" not in parsed.path:
                            raise ValueError("mailto biçimi geçersiz")
                        continue
                    if parsed.scheme or parsed.netloc:
                        raise ValueError("desteklenmeyen URL şeması veya ağ yolu")
                    target = (md.parent / unquote(parsed.path)).resolve() if parsed.path else md.resolve()
                    if not target.is_relative_to(root):
                        raise ValueError("yerel hedef depo dışına çıkıyor")
                    if not target.exists():
                        raise ValueError("yerel hedef yok")
                    if parsed.fragment:
                        if target.suffix.lower() != ".md":
                            raise ValueError("Markdown dışı başlık hedefi doğrulanamıyor")
                        if unquote(parsed.fragment) not in anchors(target.read_text(encoding="utf-8")):
                            raise ValueError("başlık hedefi yok")
                except ValueError as ex:
                    errors.append(f"{md.relative_to(root)} -> {value}: {ex}")
    return errors, checked
