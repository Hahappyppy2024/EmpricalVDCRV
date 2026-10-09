import { api, toast } from "./app.js";

const form = document.getElementById("reset-request-form");
const output = document.getElementById("reset-output");
if (form) {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(form).entries());
      const result = await api.post("/chat/accounts/password/reset/request", data);
      output.textContent = JSON.stringify(result, null, 2);
      toast("Reset request processed", "success");
    } catch (err) {
      toast(err.message, "error");
    }
  });
}
