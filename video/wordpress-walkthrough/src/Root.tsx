import React from "react";
import { Composition } from "remotion";
import { FPS, HEIGHT, WIDTH } from "./brand";
import { Thumbnail } from "./Thumbnail";
import { Walkthrough, walkthroughDuration } from "./Walkthrough";

export const RemotionRoot: React.FC = () => {
  return (
    <>
      <Composition
        id="FormlyxWordPressWalkthrough"
        component={Walkthrough}
        durationInFrames={walkthroughDuration()}
        fps={FPS}
        width={WIDTH}
        height={HEIGHT}
      />
      <Composition
        id="FormlyxWordPressThumbnail"
        component={Thumbnail}
        durationInFrames={1}
        fps={FPS}
        width={WIDTH}
        height={HEIGHT}
      />
    </>
  );
};
