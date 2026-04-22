import React, { useState } from 'react';
import { ResizableBox } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

function getPersistedWidth() {
	try {
		const w = parseInt(
			window.localStorage.getItem('wpgraphql_ide_panel_width'),
			10
		);
		return w > 0 ? w : 280;
	} catch {
		return 280;
	}
}

/**
 * Collapsible side panel — shows the active panel's content.
 *
 * Returns null when no panel is active so the editor area expands to fill
 * the space. The vertical activity bar in IDELayout always remains visible
 * and owns the toggle buttons.
 */
const ActivityPanel = () => {
	const [panelWidth, setPanelWidth] = useState(getPersistedWidth);

	const visiblePanel = useSelect(
		(select) => select('wpgraphql-ide/activity-bar').visiblePanel(),
		[]
	);

	if (!visiblePanel) {
		return null;
	}

	const PluginContent = visiblePanel.content;

	return (
		<ResizableBox
			size={{ width: panelWidth, height: '100%' }}
			minWidth={200}
			maxWidth={600}
			enable={{ right: true }}
			onResizeStop={(e, d, elt) => {
				const w = elt.offsetWidth;
				setPanelWidth(w);
				try {
					window.localStorage.setItem(
						'wpgraphql_ide_panel_width',
						String(w)
					);
				} catch {
					// localStorage unavailable
				}
			}}
			className="wpgraphql-ide-side-panel"
		>
			{/* Panel title */}
			<div className="wpgraphql-ide-side-panel-header">
				<span className="wpgraphql-ide-side-panel-title">
					{visiblePanel.title}
				</span>
			</div>

			{/* Panel content */}
			<div className="wpgraphql-ide-plugin">
				{PluginContent ? <PluginContent /> : null}
			</div>
		</ResizableBox>
	);
};

export default ActivityPanel;
