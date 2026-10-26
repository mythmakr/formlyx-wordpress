import React from "react";
import {
  AbsoluteFill,
  Audio,
  Sequence,
  interpolate,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";
import { fade } from "@remotion/transitions/fade";
import { linearTiming, TransitionSeries } from "@remotion/transitions";
import { COLORS } from "./brand";
import clipFile from "./clips.json";
import { Caption } from "./components/Caption";
import { Footage, type ClipPart } from "./components/Footage";
import { sceneMasks } from "./masks";
import { TitleCard } from "./scenes/TitleCard";

const FADE = 12;

type ClipName = "intro" | "plugins" | "connect" | "form" | "editor" | "live";

type Clip = {
  file: string;
  startFrom: number;
  duration: number;
  parts?: ClipPart[];
};

const clipLength = (clip: Clip) =>
  clip.parts && clip.parts.length > 0
    ? clip.parts.reduce((sum, part) => sum + part.duration, 0)
    : clip.duration;

const clips = clipFile as Record<ClipName, Clip>;

// Narration integrates at -24.0 LUFS. The music file integrates at -19.8 LUFS,
// so 0.044 puts the underscore about 23 dB below the voice.
const MUSIC_VOLUME = 0.044;

const narrationCues: Record<
  ClipName | "outro",
  { file: string; from: number; duration: number }[]
> = {
  intro: [{ file: "audio/narration/intro.mp3", from: 10, duration: 76 }],
  plugins: [{ file: "audio/narration/plugins-revised.mp3", from: 18, duration: 293 }],
  connect: [
    { file: "audio/narration/connect-1.mp3", from: 12, duration: 68 },
    { file: "audio/narration/connect-2-revised.mp3", from: 162, duration: 65 },
    { file: "audio/narration/connect-3.mp3", from: 402, duration: 52 },
  ],
  form: [{ file: "audio/narration/form-revised.mp3", from: 16, duration: 159 }],
  editor: [
    { file: "audio/narration/editor-1-revised.mp3", from: 14, duration: 155 },
    { file: "audio/narration/editor-2.mp3", from: 252, duration: 60 },
    { file: "audio/narration/editor-3.mp3", from: 436, duration: 48 },
  ],
  live: [
    { file: "audio/narration/live-1.mp3", from: 10, duration: 78 },
    { file: "audio/narration/live-2-revised.mp3", from: 168, duration: 126 },
  ],
  outro: [{ file: "audio/narration/outro.mp3", from: 12, duration: 127 }],
};

const Narration: React.FC<{ file: string }> = ({ file }) => {
  const frame = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();
  const fadeIn = interpolate(frame, [0, 3], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const fadeOut = interpolate(
    frame,
    [Math.max(0, durationInFrames - 5), durationInFrames - 1],
    [1, 0],
    { extrapolateLeft: "clamp", extrapolateRight: "clamp" },
  );
  return <Audio src={staticFile(file)} volume={Math.min(fadeIn, fadeOut)} />;
};

const Soundtrack: React.FC = () => {
  const frame = useCurrentFrame();
  const { durationInFrames } = useVideoConfig();
  const fadeIn = interpolate(frame, [0, 45], [0, MUSIC_VOLUME], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const fadeOut = interpolate(
    frame,
    [durationInFrames - 60, durationInFrames - 1],
    [MUSIC_VOLUME, 0],
    { extrapolateLeft: "clamp", extrapolateRight: "clamp" },
  );
  return (
    <Audio src={staticFile("audio/music.mp3")} volume={Math.min(fadeIn, fadeOut)} />
  );
};

const footageScenes: { key: ClipName; caption: string }[] = [
  { key: "intro", caption: "Add beautiful forms to WordPress with Formlyx." },
  {
    key: "plugins",
    caption: "Install and activate Formlyx Forms. This demo site already has it active.",
  },
  {
    key: "connect",
    caption: "Connect with Formlyx OAuth. WordPress never asks for an API key.",
  },
  {
    key: "form",
    caption: "Create the form you need in Formlyx's visual builder.",
  },
  {
    key: "editor",
    caption: "Add the Formlyx block, choose any published form, and set how it looks.",
  },
  {
    key: "live",
    caption: "A successful submission shows a confirmation message.",
  },
];

export const outroDuration = 5 * 30;

export const walkthroughDuration = () => {
  const footage = footageScenes.reduce((sum, scene) => {
    return sum + clipLength(clips[scene.key] as Clip);
  }, 0);
  return footage + outroDuration - FADE * footageScenes.length;
};

export const Walkthrough: React.FC = () => {
  return (
    <AbsoluteFill style={{ backgroundColor: COLORS.mist }}>
      <Soundtrack />
      <TransitionSeries>
        {footageScenes.map((scene) => {
          const clip = clips[scene.key];
          return (
            <React.Fragment key={scene.key}>
              <TransitionSeries.Sequence durationInFrames={clipLength(clip)}>
                <Footage
                  file={clip.file}
                  startFrom={clip.startFrom}
                  parts={clip.parts}
                  masks={sceneMasks[scene.key]}
                />
                <Caption text={scene.caption} />
                {narrationCues[scene.key].map((cue) => (
                  <Sequence
                    key={cue.file}
                    from={cue.from}
                    durationInFrames={cue.duration}
                  >
                    <Narration file={cue.file} />
                  </Sequence>
                ))}
              </TransitionSeries.Sequence>
              <TransitionSeries.Transition
                presentation={fade()}
                timing={linearTiming({ durationInFrames: FADE })}
              />
            </React.Fragment>
          );
        })}
        <TransitionSeries.Sequence durationInFrames={outroDuration}>
          <TitleCard
            kicker="Formlyx Forms for WordPress"
            title="Build forms at formlyx.com"
            lines={["Add them to WordPress in minutes."]}
          />
          {narrationCues.outro.map((cue) => (
            <Sequence key={cue.file} from={cue.from} durationInFrames={cue.duration}>
              <Narration file={cue.file} />
            </Sequence>
          ))}
        </TransitionSeries.Sequence>
      </TransitionSeries>
    </AbsoluteFill>
  );
};
