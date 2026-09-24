import type { Router } from 'vue-router';
import { api } from './client';
/** Progressive enhancement: tools use the same server authorization as the UI. */
export function registerNadiTools(router: Router) {
  const context = (
    navigator as Navigator & { modelContext?: { registerTool: (tool: unknown) => void } }
  ).modelContext;
  if (!context) return;
  context.registerTool({
    name: 'nadi_public_statistics',
    description:
      'Read the current safe aggregate ASN statistics. Returns synthetic data in demo mode; never returns employee identities.',
    inputSchema: {
      type: 'object',
      properties: {
        unit: { type: 'string' },
        jenis: { type: 'string', enum: ['PNS', 'PPPK Penuh Waktu', 'PPPK Paruh Waktu'] },
      },
      additionalProperties: false,
    },
    annotations: { readOnlyHint: true },
    execute: async (args: { unit?: string; jenis?: string }) => ({
      content: [
        {
          type: 'text',
          text: JSON.stringify(await api('public/statistics?' + new URLSearchParams(args))),
        },
      ],
    }),
  });
  context.registerTool({
    name: 'nadi_apply_public_filters',
    description: 'Apply the unit and ASN type filters to the visible public dashboard.',
    inputSchema: {
      type: 'object',
      properties: {
        unit: { type: 'string' },
        jenis: { type: 'string', enum: ['PNS', 'PPPK Penuh Waktu', 'PPPK Paruh Waktu'] },
      },
      additionalProperties: false,
    },
    execute: async (args: { unit?: string; jenis?: string }) => {
      await api('public/statistics?' + new URLSearchParams(args));
      await router.push({ path: '/', query: args });
      return { content: [{ type: 'text', text: 'Filter statistik diterapkan.' }] };
    },
  });
}
