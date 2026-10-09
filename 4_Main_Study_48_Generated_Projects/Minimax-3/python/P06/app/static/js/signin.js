import { api, postState, toast } from "./app.js";

const form = document.getElementById("signin-form");
if (form) {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    postState("accounts.signin", "loading");
    try {
      const data = Object.fromEntries(new FormData(form).entries());
      await api.post("/chat/accounts/signin", data);
      postState("accounts.signin", "success");
      toast("Welcome back", "success");
      window.location.href = "/app";
    } catch (err) {
      postState("accounts.signin", "error", { message: err.message });
      toast(err.message, "error");
    }
  });
}
