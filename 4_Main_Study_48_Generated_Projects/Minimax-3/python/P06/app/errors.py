from __future__ import annotations


class ServiceError(Exception):
    code = "service_error"
    http_status = 400

    def __init__(self, message: str, *, code: str | None = None, http_status: int | None = None):
        super().__init__(message)
        self.message = message
        if code is not None:
            self.code = code
        if http_status is not None:
            self.http_status = http_status


class ValidationError(ServiceError):
    code = "validation_error"
    http_status = 400


class AuthError(ServiceError):
    code = "auth_error"
    http_status = 401


class ForbiddenError(ServiceError):
    code = "forbidden"
    http_status = 403


class NotFoundError(ServiceError):
    code = "not_found"
    http_status = 404


class ConflictError(ServiceError):
    code = "conflict"
    http_status = 409
