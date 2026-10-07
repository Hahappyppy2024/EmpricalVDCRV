Aligned Requirement-level Functional Tests (v2)

This version is robust to PHP 8.5 deprecation warnings being printed as HTML (display_errors=On):
- JSON parsing extracts the first {...} object from the response.
- CSV tests validate the response body content rather than Content-Type.

If you want clean headers, fix CsvController fgetcsv/fputcsv deprecations or turn off display_errors.
