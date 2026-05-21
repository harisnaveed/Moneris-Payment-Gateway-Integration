!(function () {
  var e = document.currentScript,
    t = (e.getAttribute("data-widget-id"), document.createElement("iframe"));
  ((t.id = "edh-iframe"),
    (t.src = "https://yourdomain.com/"),
    (t.style.width = "100%"),
    (t.style.border = "none"),
    (t.style.display = "block"),
    e.parentNode.insertBefore(t, e.nextSibling),
    window.addEventListener("message", function (e) {
      if ("string" == typeof e.data) {
        if (0 === e.data.indexOf("resize::")) {
          var i = e.data.replace("resize::", "");
          t.style.height = i + "px";
        }
        0 === e.data.indexOf("scrollto::") &&
          t.scrollIntoView({ behavior: "smooth" });
      }
    }));
})();
