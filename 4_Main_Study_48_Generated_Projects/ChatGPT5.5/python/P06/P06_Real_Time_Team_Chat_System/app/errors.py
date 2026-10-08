class ApiError(Exception):
    def __init__(self, status, code, message, fields=None):
        super().__init__(message)
        self.status, self.code, self.message = status, code, message
        self.fields = fields or {}


def require(data, *fields):
    missing = {name: "required" for name in fields if data.get(name) in (None, "")}
    if missing:
        raise ApiError(422, "validation_error", "Required fields are missing.", missing)
