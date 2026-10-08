"""Stable, deterministic error handling for the application."""

from __future__ import annotations

from flask import jsonify, render_template, request


class AppError(Exception):
    """Business-rule violation with a stable user-facing code and message."""

    def __init__(self, message: str, code: str = "validation_error", status: int = 400):
        super().__init__(message)
        self.message = message
        self.code = code
        self.status = status


def is_api_request() -> bool:
    return request.path.startswith("/api/")


def register_error_handlers(app) -> None:
    @app.errorhandler(AppError)
    def handle_app_error(error: AppError):
        if is_api_request():
            return (
                jsonify(
                    {
                        "ok": False,
                        "error": {"code": error.code, "message": error.message},
                    }
                ),
                error.status,
            )
        return (
            render_template(
                "error_page.html",
                title="Request failed",
                code=str(error.status),
                message=error.message,
            ),
            error.status,
        )

    @app.errorhandler(400)
    @app.errorhandler(401)
    @app.errorhandler(403)
    @app.errorhandler(404)
    @app.errorhandler(405)
    def handle_http_error(error):
        messages = {
            400: "Bad request",
            401: "Authentication required",
            403: "You are not allowed to perform this action",
            404: "The requested resource was not found",
            405: "Method not allowed",
        }
        message = messages.get(error.code, error.name or "Request failed")
        if is_api_request():
            return (
                jsonify(
                    {
                        "ok": False,
                        "error": {
                            "code": f"http_{error.code}",
                            "message": message,
                        },
                    }
                ),
                error.code,
            )
        return (
            render_template(
                "error_page.html",
                title="Something went wrong",
                code=str(error.code),
                message=message,
            ),
            error.code,
        )

    @app.errorhandler(413)
    def handle_too_large(error):
        message = "The uploaded file is too large"
        if is_api_request():
            return (
                jsonify(
                    {
                        "ok": False,
                        "error": {"code": "file_too_large", "message": message},
                    }
                ),
                413,
            )
        return (
            render_template(
                "error_page.html",
                title="Upload failed",
                code="413",
                message=message,
            ),
            413,
        )

    @app.errorhandler(500)
    def handle_internal_error(error):
        if is_api_request():
            return (
                jsonify(
                    {
                        "ok": False,
                        "error": {
                            "code": "internal_error",
                            "message": "An unexpected error occurred",
                        },
                    }
                ),
                500,
            )
        return (
            render_template(
                "error_page.html",
                title="Something went wrong",
                code="500",
                message="An unexpected error occurred. Please try again later.",
            ),
            500,
        )
