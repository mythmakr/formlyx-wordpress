import React from "react";
import {
  AbsoluteFill,
  OffthreadVideo,
  interpolate,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";
import type { Mask } from "../masks";

export type ClipPart = {
  file?: string;
  startFrom: number;
  duration: number;
};

export const Footage: React.FC<{
  file: string;
  startFrom: number;
  parts?: ClipPart[];
  masks?: Mask[][];
}> = ({ file, startFrom, parts, masks }) => {
  const frame = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();
  const scale = interpolate(frame, [0, durationInFrames], [1, 1.012], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const sequence =
    parts && parts.length > 0
      ? parts
      : [{ file, startFrom, duration: durationInFrames }];
  let cursor = 0;
  let partIndex = 0;
  let part = sequence[0];
  for (const [index, candidate] of sequence.entries()) {
    if (frame < cursor + candidate.duration) {
      part = candidate;
      partIndex = index;
      break;
    }
    cursor += candidate.duration;
    partIndex = index;
  }
  const activeMasks = masks?.[partIndex] ?? [];

  return (
    <AbsoluteFill style={{ backgroundColor: "#f7f7f5" }}>
      <AbsoluteFill style={{ transform: `scale(${scale})` }}>
        <OffthreadVideo
          src={staticFile(part.file || file)}
          startFrom={part.startFrom - cursor}
          style={{ width: "100%", height: "100%", objectFit: "cover" }}
        />
        {activeMasks.map((mask) => (
          <div
            key={`${mask.x}-${mask.y}-${mask.width}`}
            style={{
              position: "absolute",
              left: mask.x,
              top: mask.y,
              width: mask.width,
              height: mask.height,
              backgroundColor: mask.color,
            }}
          />
        ))}
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
