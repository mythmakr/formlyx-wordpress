export type Mask = {
  x: number;
  y: number;
  width: number;
  height: number;
  color: string;
};

const WP_BAR = "#1d2327";
const EDITOR_BAR = "#1e1e1e";
const MENU = "#0c0c0c";
const PAGE = "#ffffff";
const FIELD = "#f0f0f0";

const adminHostname = (color: string): Mask => ({
  x: 40,
  y: 0,
  width: 214,
  height: 32,
  color,
});

const peerKitMenu: Mask = {
  x: 0,
  y: 668,
  width: 160,
  height: 30,
  color: MENU,
};

const settingsMasks = [adminHostname(EDITOR_BAR), peerKitMenu];

const editorUrl: Mask = {
  x: 1628,
  y: 186,
  width: 280,
  height: 54,
  color: PAGE,
};

const pageAddress: Mask = {
  x: 1648,
  y: 212,
  width: 196,
  height: 38,
  color: FIELD,
};

const publicHostname: Mask = {
  x: 268,
  y: 12,
  width: 360,
  height: 44,
  color: PAGE,
};

const publicPeerKitNav: Mask = {
  x: 1024,
  y: 8,
  width: 666,
  height: 46,
  color: PAGE,
};

const publicFooterHostname: Mask = {
  x: 240,
  y: 896,
  width: 580,
  height: 88,
  color: PAGE,
};

export const sceneMasks: Record<string, Mask[][]> = {
  intro: [[]],
  plugins: [[adminHostname(WP_BAR)]],
  connect: [settingsMasks, [], settingsMasks],
  form: [[]],
  editor: [[adminHostname(EDITOR_BAR)], [adminHostname(EDITOR_BAR), editorUrl], [adminHostname(EDITOR_BAR), pageAddress]],
  live: [[publicHostname, publicPeerKitNav], [publicFooterHostname]],
};
