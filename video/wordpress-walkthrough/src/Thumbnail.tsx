import React from "react";
import { AbsoluteFill, Img, staticFile } from "remotion";
import { COLORS, FONT, SANS } from "./brand";

export const Thumbnail: React.FC = () => {
  return (
    <AbsoluteFill style={{ backgroundColor: COLORS.mist, fontFamily: SANS }}>
      <AbsoluteFill style={{ padding: 72, justifyContent: "space-between" }}>
        <Img src={staticFile("logo.png")} style={{ width: 280, height: "auto" }} />
        <div
          style={{
            color: COLORS.ink,
            fontFamily: FONT,
            fontSize: 78,
            lineHeight: 1.02,
            maxWidth: 980,
          }}
        >
          Add beautiful forms to WordPress
        </div>
        <div style={{ color: COLORS.brand, fontSize: 32, fontWeight: 650 }}>
          Formlyx Forms · formlyx.com
        </div>
      </AbsoluteFill>
      <div
        style={{
          position: "absolute",
          width: 860,
          height: 484,
          right: 72,
          bottom: 150,
          overflow: "hidden",
          borderRadius: 20,
          border: "8px solid rgba(255,255,255,0.9)",
          boxShadow: "0 24px 60px rgba(0,0,0,0.28)",
        }}
      >
        <Img
          src={staticFile("captures/success.png")}
          style={{
            width: 1048,
            height: 590,
            objectFit: "cover",
            objectPosition: "center top",
          }}
        />
      </div>
    </AbsoluteFill>
  );
};
