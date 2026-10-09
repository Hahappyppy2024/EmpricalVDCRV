// Dataset upload page.
(function () {
  const form = document.getElementById('upload-form');
  if (!form) return;
  const statusNode = form.querySelector('[data-role="status"]');

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    const formData = new FormData(form);
    try {
      const response = await App.api.post('/api/data/dataset_upload', formData);
      App.status(statusNode, 'Uploaded dataset ' + response.data.dataset.name + '.', 'success');
      form.reset();
      setTimeout(function () { window.location.href = '/datasets/' + response.data.dataset.id; }, 700);
    } catch (err) {
      App.status(statusNode, err.message, 'error');
    }
  });
})();
