import { api, postState, toast } from "./app.js";

const form = document.getElementById("register-form");
if (form) {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    postState("accounts.register", "loading");
    try {
      const data = Object.fromEntries(new FormData(form).entries());
      await api.post("/chat/accounts", data);
      postState("accounts.register", "success");
      toast("Account created", "success");
      window.location.href = "/app";
    } catch (err) {
      postState("accounts.register", "error", { message: err.message });
      toast(err.message, "error");
    }
  });
}
