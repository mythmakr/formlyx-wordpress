import React from "react";
import { AbsoluteFill, interpolate, useCurrentFrame } from "remotion";
import { COLORS, SANS } from "../brand";

export const Caption: React.FC<{ text: string }> = ({ text }) => {
  const frame = useCurrentFrame();
  const opacity = interpolate(frame, [0, 8], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  return (
    <AbsoluteFill
      style={{
        justifyContent: "flex-end",
        pointerEvents: "none",
        opacity,
      }}
    >
      <div
        style={{
          margin: "0 64px 48px",
          maxWidth: 1500,
          background: "rgba(31, 41, 55, 0.92)",
          color: COLORS.white,
          fontFamily: SANS,
          fontSize: 38,
          lineHeight: 1.25,
          fontWeight: 650,
          letterSpacing: -0.3,
          padding: "20px 28px",
          borderRadius: 18,
          borderLeft: `8px solid ${COLORS.clay}`,
        }}
      >
        {text}
      </div>
    </AbsoluteFill>
  );
};
