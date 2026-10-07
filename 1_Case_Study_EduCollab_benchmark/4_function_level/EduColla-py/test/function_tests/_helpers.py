import io
import zipfile


def make_zip_bytes(files: dict[str, bytes]) -> io.BytesIO:
    """Create an in-memory zip containing {path: bytes}."""
    bio = io.BytesIO()
    with zipfile.ZipFile(bio, "w", zipfile.ZIP_DEFLATED) as zf:
        for name, data in files.items():
            zf.writestr(name, data)
    bio.seek(0)
    return bio
