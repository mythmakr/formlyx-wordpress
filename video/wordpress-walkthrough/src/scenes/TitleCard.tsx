import React from "react";
import {
  AbsoluteFill,
  Img,
  interpolate,
  staticFile,
  useCurrentFrame,
} from "remotion";
import { COLORS, FONT, SANS } from "../brand";

export const TitleCard: React.FC<{
  kicker: string;
  title: string;
  lines: string[];
}> = ({ kicker, title, lines }) => {
  const frame = useCurrentFrame();
  const opacity = interpolate(frame, [0, 12], [0, 1], {
    extrapolateRight: "clamp",
  });
  const rise = interpolate(frame, [0, 16], [18, 0], {
    extrapolateRight: "clamp",
  });

  return (
    <AbsoluteFill
      style={{
        background: COLORS.mist,
        color: COLORS.ink,
        fontFamily: SANS,
        opacity,
        justifyContent: "center",
        padding: "0 140px",
      }}
    >
      <Img
        src={staticFile("logo.png")}
        style={{ width: 280, height: "auto", marginBottom: 36 }}
      />
      <div
        style={{
          transform: `translateY(${rise}px)`,
          fontFamily: FONT,
          fontSize: 76,
          lineHeight: 1.05,
          maxWidth: 1400,
        }}
      >
        {title}
      </div>
      <div
        style={{
          marginTop: 28,
          fontSize: 22,
          letterSpacing: 3,
          textTransform: "uppercase",
          color: COLORS.brand,
          fontWeight: 700,
        }}
      >
        {kicker}
      </div>
      <div style={{ marginTop: 36, fontSize: 36, lineHeight: 1.35, maxWidth: 1100 }}>
        {lines.map((line) => (
          <div key={line}>{line}</div>
        ))}
      </div>
    </AbsoluteFill>
  );
};
