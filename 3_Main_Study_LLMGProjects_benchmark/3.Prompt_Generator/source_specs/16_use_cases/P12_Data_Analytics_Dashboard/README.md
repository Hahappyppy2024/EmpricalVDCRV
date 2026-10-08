# P12 — Data Analytics Dashboard — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| DATA-01 | Account access | Analyst; viewer; admin | POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| DATA-02 | Dataset upload | Analyst | POST /api/datasets; GET /api/datasets/{datasetId}/ingestion |
| DATA-03 | Dataset catalog | Analyst; viewer | GET /api/datasets?q=&owner=&status=&page=; GET /api/datasets/{datasetId}; PATCH /api/datasets/{datasetId}; POST /api/datasets/{datasetId}/archive |
| DATA-04 | Data preview | Analyst; viewer | GET /api/datasets/{datasetId}/preview?offset=&limit=; GET /api/datasets/{datasetId}/schema |
| DATA-05 | Filter builder | Analyst | POST /api/datasets/{datasetId}/query-preview; PUT /api/charts/{chartId}/filters |
| DATA-06 | Chart builder | Analyst | POST /api/charts; GET /api/charts/{chartId}; PATCH /api/charts/{chartId}; POST /api/charts/{chartId}/data |
| DATA-07 | Calculated columns | Analyst | GET /api/datasets/{datasetId}/calculated-columns; POST /api/datasets/{datasetId}/calculated-columns; PATCH /api/calculated-columns/{columnId}; DELETE /api/calculated-columns/{columnId} |
| DATA-08 | Dashboard sharing | Analyst; viewer | POST /api/dashboards; PATCH /api/dashboards/{dashboardId}; POST /api/dashboards/{dashboardId}/shares; DELETE /api/dashboard-shares/{shareId}; GET /api/dashboards/{dashboardId} |
| DATA-09 | Export | Analyst; viewer | GET /api/charts/{chartId}/export?format=csv; GET /api/dashboards/{dashboardId}/export?format=pdf |
| DATA-10 | Data source connections | Analyst; admin | GET /api/data-sources; POST /api/data-sources; PATCH /api/data-sources/{sourceId}; POST /api/data-sources/{sourceId}/test; DELETE /api/data-sources/{sourceId} |
| DATA-11 | Audit and lineage | Analyst; admin | GET /api/datasets/{datasetId}/lineage; GET /api/workspaces/{workspaceId}/audit-events |
| DATA-12 | Admin operations | Admin | GET /api/admin/workspaces; POST /api/admin/workspaces; PATCH /api/admin/workspaces/{workspaceId}; PATCH /api/admin/workspaces/{workspaceId}/members/{userId}; PATCH /api/admin/connectors/{connectorId} |
