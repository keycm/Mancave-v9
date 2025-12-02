document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("inquiryForm");

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    fetch(form.action, {
      method: "POST",
      body: new FormData(form),
    })
      .then((res) => res.text())
      .then((txt) => {
        if (txt.trim() === "success") {
          alert("Thank you! We'll get back to you soon.");
          form.reset();
        } else {
          alert("Please check your input and try again.");
        }
      })
      .catch(() => {
        alert("Something went wrong. Please try later.");
      });
  });
});

 