<?php
header('Content-Type: application/javascript');
?>

import { initializeApp } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-app.js";
import {
  getAuth,
  GoogleAuthProvider,
  signInWithPopup,
} from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyBVH0o1M4fCWrxUNP2jF0VpVpRCBOVN8UY",
  authDomain: "skill-swap-email-login.firebaseapp.com",
  projectId: "skill-swap-email-login",
  storageBucket: "skill-swap-email-login.firebasestorage.app",
  messagingSenderId: "998351948212",
  appId: "1:998351948212:web:8b60d3a390e3d310100ae1",
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
auth.languageCode = "en";
const provider = new GoogleAuthProvider();

const googleLoginBtn = document.getElementById("google-login-btn");

if (googleLoginBtn) {
  googleLoginBtn.addEventListener("click", async () => {
    try {
      const result  = await signInWithPopup(auth, provider);
      const idToken = await result.user.getIdToken();

      const response = await fetch("/skill_swap/Auth/google_auth", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_token: idToken }),
      });

      const text = await response.text();
      console.log("SERVER RESPONSE:", text);

      if (!text) {
        alert("Server returned an empty response. Check PHP error logs.");
        return;
      }

      const data = JSON.parse(text);

      if (data.success) {
        window.location.href = data.redirect;
      } else {
        alert("Login failed: " + data.message);
      }
    } catch (error) {
      console.error("Google Login Error:", error.code, error.message);
      alert("Login failed: " + error.message);
    }
  });
} else {
  console.warn("Google login button not found.");
}