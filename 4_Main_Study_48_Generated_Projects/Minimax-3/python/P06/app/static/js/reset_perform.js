import { api, toast } from "./app.js";

const form = document.getElementById("reset-perform-form");
if (form) {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(form).entries());
      await api.post("/chat/accounts/password/reset", data);
      toast("Password reset", "success");
      window.location.href = "/signin";
    } catch (err) {
      toast(err.message, "error");
    }
  });
}
