/* P12 Data Analytics Dashboard - browser client (vanilla JavaScript) */
"use strict";

function esc(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

async function api(url, method, body) {
  const opts = { method: method || "GET", headers: {} };
  if (body) opts.body = body;
  const res = await fetch(url, opts);
  let data = null;
  try {
    data = await res.json();
  } catch (e) {
    data = { ok: false, error: "Unexpected server response" };
  }
  if (!res.ok || data.ok === false) {
    throw new Error(data.error || "Request failed");
  }
  return data;
}

function errorBox(form) {
  let box = form.querySelector(".form-error");
  if (!box) {
    box = document.createElement("div");
    box.className = "form-error";
    form.appendChild(box);
  }
  return box;
}

function initForms() {
  document.querySelectorAll("form[data-api]").forEach(function (form) {
    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      const box = errorBox(form);
      box.textContent = "";
      try {
        await api(form.getAttribute("action"), form.method, new FormData(form));
        window.location.reload();
      } catch (err) {
        box.textContent = err.message;
      }
    });
  });
}

function initInlineForms() {
  document.querySelectorAll("form[data-inline]").forEach(function (form) {
    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      const target = document.getElementById(form.getAttribute("data-target"));
      if (target) target.innerHTML = "Working…";
      try {
        const data = await api(form.getAttribute("action"), form.method, new FormData(form));
        if (target) {
          let html = "";
          if (data.sample_rows) {
            html += "<strong>Connected. Sample rows:</strong><pre>" +
              esc(JSON.stringify({ columns: data.sample_columns, rows: data.sample_rows }, null, 2)) +
              "</pre>";
          } else {
            html += "<pre>" + esc(JSON.stringify(data, null, 2)) + "</pre>";
          }
          target.innerHTML = html;
        }
      } catch (err) {
        if (target) target.innerHTML = '<span class="form-error">' + esc(err.message) + "</span>";
      }
    });
  });
}

function initCharts() {
  document.querySelectorAll(".chart-render").forEach(function (el) {
    let series = null;
    try {
      series = JSON.parse(el.dataset.chart || "null");
    } catch (e) {
      series = null;
    }
    drawChart(el, series, el.dataset.type || "bar");
  });
}

function drawChart(el, series, chartType) {
  if (!el) return;
  if (!series || !series.labels || !series.labels.length) {
    el.innerHTML = '<div class="empty">No data to chart.</div>';
    return;
  }
  const labels = series.labels;
  const values = series.values;
  const W = 560, H = 260, padL = 60, padR = 24, padT = 24, padB = 46;
  const plotW = W - padL - padR;
  const plotH = H - padT - padB;
  const max = Math.max.apply(null, values.concat([1]));
  const colors = ["#4c72b0", "#55a868", "#c44e52", "#8172b2", "#ccb974", "#64b5cd", "#dd8452"];
  let svg = '<svg width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + " " + H + '" role="img">';

  if (chartType === "pie") {
    const total = values.reduce(function (a, b) { return a + b; }, 0) || 1;
    const cx = W / 2, cy = H / 2 - 10;
    const r = Math.min(plotW, plotH) / 2;
    let start = -Math.PI / 2;
    values.forEach(function (v, i) {
      const angle = (v / total) * 2 * Math.PI;
      const x0 = cx + r * Math.cos(start), y0 = cy + r * Math.sin(start);
      const x1 = cx + r * Math.cos(start + angle), y1 = cy + r * Math.sin(start + angle);
      const large = angle > Math.PI ? 1 : 0;
      svg += '<path d="M ' + cx + " " + cy + " L " + x0.toFixed(2) + " " + y0.toFixed(2) +
        " A " + r + " " + r + " 0 " + large + " 1 " + x1.toFixed(2) + " " + y1.toFixed(2) +
        ' Z" fill="' + colors[i % colors.length] + '" stroke="#fff" stroke-width="1"></path>';
      start += angle;
    });
    labels.forEach(function (l, i) {
      const ty = padT + 8 + i * 18;
      svg += '<rect x="' + (W - 170) + '" y="' + (ty - 11) + '" width="12" height="12" fill="' + colors[i % colors.length] + '"></rect>' +
        '<text x="' + (W - 152) + '" y="' + ty + '" font-size="12">' + esc(l) + "</text>";
    });
  } else if (chartType === "line") {
    const step = plotW / Math.max(labels.length - 1, 1);
    const pts = values.map(function (v, i) {
      return (padL + i * step).toFixed(2) + "," + (padT + plotH - (v / max) * plotH).toFixed(2);
    });
    svg += '<polyline points="' + pts.join(" ") + '" fill="none" stroke="#4c72b0" stroke-width="2"></polyline>';
    values.forEach(function (v, i) {
      const x = padL + i * step;
      const y = padT + plotH - (v / max) * plotH;
      svg += '<circle cx="' + x.toFixed(2) + '" cy="' + y.toFixed(2) + '" r="4" fill="#4c72b0"></circle>' +
        '<text x="' + x.toFixed(2) + '" y="' + (y - 8).toFixed(2) + '" text-anchor="middle" font-size="11">' + v + "</text>" +
        '<text x="' + x.toFixed(2) + '" y="' + (H - padB + 16) + '" text-anchor="middle" font-size="11">' + esc(labels[i]) + "</text>";
    });
  } else {
    const slot = plotW / labels.length;
    const bw = Math.min(slot * 0.6, 60);
    values.forEach(function (v, i) {
      const x = padL + i * slot + (slot - bw) / 2;
      const h = (v / max) * plotH;
      const y = padT + plotH - h;
      svg += '<rect x="' + x.toFixed(2) + '" y="' + y.toFixed(2) + '" width="' + bw.toFixed(2) +
        '" height="' + h.toFixed(2) + '" fill="#4c72b0"></rect>' +
        '<text x="' + (x + bw / 2).toFixed(2) + '" y="' + (y - 4).toFixed(2) + '" text-anchor="middle" font-size="11">' + v + "</text>" +
        '<text x="' + (x + bw / 2).toFixed(2) + '" y="' + (H - padB + 16) + '" text-anchor="middle" font-size="11">' + esc(labels[i]) + "</text>";
    });
  }
  svg += "</svg>";
  el.innerHTML = svg;
}

/* ---- page-specific behaviour ---- */

function initChartBuilder() {
  const ds = document.getElementById("ch-dataset");
  const xSel = document.getElementById("ch-x");
  const ySel = document.getElementById("ch-y");
  const typeSel = document.getElementById("ch-type");
  const aggSel = document.getElementById("ch-agg");
  const preview = document.getElementById("chart-preview");
  if (!ds || !preview) return;

  async function refreshPreview() {
    if (!ds.value || !xSel.value || !ySel.value) {
      preview.innerHTML = '<div class="muted">Select a dataset, X and Y columns.</div>';
      return;
    }
    preview.innerHTML = "Loading…";
    const url = "/api/data/chart_builder/preview?dataset_id=" + encodeURIComponent(ds.value) +
      "&x=" + encodeURIComponent(xSel.value) +
      "&y=" + encodeURIComponent(ySel.value) +
      "&agg=" + encodeURIComponent(aggSel.value);
    try {
      const data = await api(url);
      drawChart(preview, data.series, typeSel.value);
    } catch (err) {
      preview.innerHTML = '<span class="form-error">' + esc(err.message) + "</span>";
    }
  }

  async function onDatasetChange() {
    xSel.innerHTML = '<option value="">Select column</option>';
    ySel.innerHTML = '<option value="">Select column</option>';
    if (!ds.value) return;
    try {
      const data = await api("/api/data/dataset_rows?dataset_id=" + encodeURIComponent(ds.value));
      data.columns.forEach(function (c) {
        xSel.insertAdjacentHTML("beforeend", '<option value="' + esc(c) + '">' + esc(c) + "</option>");
        ySel.insertAdjacentHTML("beforeend", '<option value="' + esc(c) + '">' + esc(c) + "</option>");
      });
      if (xSel.options.length > 1) xSel.options[1].selected = true;
      if (ySel.options.length > 1) ySel.options[1].selected = true;
      refreshPreview();
    } catch (err) {
      preview.innerHTML = '<span class="form-error">' + esc(err.message) + "</span>";
    }
  }

  ds.addEventListener("change", onDatasetChange);
  xSel.addEventListener("change", refreshPreview);
  ySel.addEventListener("change", refreshPreview);
  typeSel.addEventListener("change", refreshPreview);
  aggSel.addEventListener("change", refreshPreview);
  onDatasetChange();
}

function initFilterBuilder() {
  const ds = document.getElementById("fld-dataset");
  const expr = document.getElementById("fld-expression");
  const box = document.getElementById("filter-preview");
  if (!ds || !box) return;
  let timer = null;

  async function refresh() {
    if (!ds.value || !expr.value.trim()) {
      box.textContent = "Select a dataset and type an expression to preview the match count.";
      box.className = "preview-box muted";
      return;
    }
    try {
      const url = "/api/data/filter_builder/preview?dataset_id=" + encodeURIComponent(ds.value) +
        "&expression=" + encodeURIComponent(expr.value);
      const data = await api(url);
      box.innerHTML = "<strong>" + data.preview_count + "</strong> rows match.";
      if (data.matched_rows && data.matched_rows.length) {
        box.innerHTML += "<pre>" + esc(JSON.stringify(data.matched_rows, null, 2)) + "</pre>";
      }
      box.className = "preview-box";
    } catch (err) {
      box.innerHTML = '<span class="form-error">' + esc(err.message) + "</span>";
      box.className = "preview-box";
    }
  }

  expr.addEventListener("input", function () {
    clearTimeout(timer);
    timer = setTimeout(refresh, 350);
  });
  ds.addEventListener("change", refresh);
}

function initCalculated() {
  const ds = document.getElementById("cc-dataset");
  const expr = document.getElementById("cc-expression");
  const box = document.getElementById("calc-preview");
  if (!ds || !box) return;
  let timer = null;

  async function refresh() {
    if (!ds.value || !expr.value.trim()) {
      box.textContent = "Type an expression to preview computed values for the first rows.";
      box.className = "preview-box muted";
      return;
    }
    try {
      const url = "/api/data/calculated_columns/preview?dataset_id=" + encodeURIComponent(ds.value) +
        "&expression=" + encodeURIComponent(expr.value);
      const data = await api(url);
      box.innerHTML = "<strong>Sample values (first " + data.sample.length + " rows):</strong>" +
        "<pre>" + esc(JSON.stringify(data.sample, null, 2)) + "</pre>";
      box.className = "preview-box";
    } catch (err) {
      box.innerHTML = '<span class="form-error">' + esc(err.message) + "</span>";
      box.className = "preview-box";
    }
  }

  expr.addEventListener("input", function () {
    clearTimeout(timer);
    timer = setTimeout(refresh, 350);
  });
  ds.addEventListener("change", refresh);
}

document.addEventListener("DOMContentLoaded", function () {
  initForms();
  initInlineForms();
  initCharts();
  const page = document.body.dataset.page;
  if (page === "chart_builder") initChartBuilder();
  if (page === "filter_builder") initFilterBuilder();
  if (page === "calculated_columns") initCalculated();
});
