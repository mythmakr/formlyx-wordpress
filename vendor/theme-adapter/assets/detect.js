(function () {
  var config = window.razorThemeAdapter || {};
  var gaps = Array.isArray(config.gaps) ? config.gaps : [];
  if (!gaps.length || !window.getComputedStyle) return;

  var properties = {
    "color-primary": "--razor-theme-color-primary",
    "color-primary-contrast": "--razor-theme-color-primary-contrast",
    "color-background": "--razor-theme-color-background",
    "color-text": "--razor-theme-color-text",
    "color-border": "--razor-theme-color-border",
    "color-link": "--razor-theme-color-link",
    "font-family": "--razor-theme-font-family",
    "radius": "--razor-theme-radius",
    "button-background": "--razor-theme-button-background",
    "button-color": "--razor-theme-button-color",
    "button-border-color": "--razor-theme-button-border-color",
    "button-border-width": "--razor-theme-button-border-width",
    "button-radius": "--razor-theme-button-radius",
    "button-padding-block": "--razor-theme-button-padding-block",
    "button-padding-inline": "--razor-theme-button-padding-inline",
    "button-font-family": "--razor-theme-button-font-family",
    "button-font-size": "--razor-theme-button-font-size",
    "button-font-weight": "--razor-theme-button-font-weight"
  };

  function wanted(name) {
    return gaps.indexOf(name) !== -1 && properties[name];
  }

  function transparent(color) {
    var value = String(color || "").replace(/\s+/g, "").toLowerCase();
    return value === "" || value === "transparent" || value === "rgba(0,0,0,0)" || value === "rgba(0,0,0,0.0)";
  }

  function different(color, against) {
    if (transparent(color)) return "";
    if (against && String(color) === String(against)) return "";
    return String(color);
  }

  function read() {
    if (!document.body) return;
    var root = document.documentElement;
    var body = window.getComputedStyle(document.body);
    var probe = document.createElement("button");
    probe.type = "button";
    probe.className = "wp-element-button";
    probe.textContent = "Aa";
    probe.style.position = "absolute";
    probe.style.left = "-9999px";
    probe.style.top = "0";
    var link = document.createElement("a");
    link.href = "#razor-theme";
    link.textContent = "a";
    link.style.position = "absolute";
    link.style.left = "-9999px";
    document.body.appendChild(probe);
    document.body.appendChild(link);
    var button = window.getComputedStyle(probe);
    var anchor = window.getComputedStyle(link);
    function set(name, value) {
      if (!wanted(name) || !value) return;
      root.style.setProperty(properties[name], value);
    }
    set("color-background", different(body.backgroundColor, ""));
    set("color-text", different(body.color, ""));
    set("font-family", body.fontFamily || "");
    var buttonBackground = different(button.backgroundColor, body.backgroundColor);
    var linkColor = different(anchor.color, body.color);
    set("button-background", buttonBackground);
    set("color-primary", buttonBackground || linkColor);
    set("button-color", different(button.color, "") || "");
    set("color-primary-contrast", different(button.color, body.color));
    set("color-link", linkColor);
    set("button-border-color", different(button.borderTopColor, button.backgroundColor));
    if (wanted("color-border") && button.borderTopStyle && button.borderTopStyle !== "none") {
      set("color-border", different(button.borderTopColor, button.backgroundColor));
    }
    set("button-border-width", button.borderTopWidth || "");
    set("button-radius", button.borderTopLeftRadius || "");
    set("radius", button.borderTopLeftRadius || "");
    set("button-padding-block", button.paddingTop || "");
    set("button-padding-inline", button.paddingLeft || "");
    set("button-font-family", button.fontFamily || "");
    set("button-font-size", button.fontSize || "");
    set("button-font-weight", button.fontWeight || "");
    probe.remove();
    link.remove();
  }

  if (document.body) read();
  else document.addEventListener("DOMContentLoaded", read);
})();
