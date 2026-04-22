import React from 'react';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { dateI18n } from '@wordpress/date';
import { Icon, backup } from '@wordpress/icons';

export const HistoryIcon = () => <Icon icon={backup} />;

function extractOperationName(query) {
	if (!query) {
		return null;
	}
	const match = query.match(/(?:query|mutation|subscription)\s+(\w+)/);
	return match ? match[1] : null;
}

/**
 * Global execution history across all open documents.
 *
 * Entries are sorted newest-first. Clicking an entry switches to that
 * document and restores its query/variables/headers in the editors.
 */
export function HistoryPanel() {
	const allDocuments = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getDocuments(),
		[]
	);
	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);

	const { setQuery, setVariables, setHeaders } =
		useDispatch('wpgraphql-ide/app');
	const { saveDocument, switchTab } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const allHistory = allDocuments
		.flatMap((doc) =>
			(doc.history || []).map((entry) => ({
				...entry,
				docId: doc.id,
				docLabel:
					extractOperationName(entry.query) ||
					doc.title ||
					'Untitled',
			}))
		)
		.sort((a, b) => b.timestamp - a.timestamp);

	const clearAllHistory = () => {
		allDocuments.forEach((doc) => {
			if ((doc.history || []).length > 0) {
				saveDocument(doc.id, { history: [] });
			}
		});
	};

	const restoreEntry = (entry) => {
		if (String(entry.docId) !== String(activeDocument?.id)) {
			switchTab(String(entry.docId));
		}
		setQuery(entry.query || '');
		setVariables(entry.variables || '');
		setHeaders(entry.headers || '');
	};

	if (allHistory.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel">
				<p className="wpgraphql-ide-history-empty">
					No executions yet. Run a query to see history.
				</p>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel">
			<div className="wpgraphql-ide-history-actions">
				<Button
					variant="link"
					isDestructive
					onClick={clearAllHistory}
					size="small"
				>
					Clear all history
				</Button>
			</div>
			<ul className="wpgraphql-ide-history-list">
				{allHistory.map((entry, index) => (
					<li
						key={`${entry.docId}-${entry.timestamp}-${index}`}
						className="wpgraphql-ide-history-entry"
					>
						<button
							type="button"
							className="wpgraphql-ide-history-entry-button"
							onClick={() => restoreEntry(entry)}
						>
							<div className="wpgraphql-ide-history-entry-header">
								<span
									className={`wpgraphql-ide-history-status wpgraphql-ide-history-status--${entry.status}`}
								>
									{entry.status === 'success' ? 'OK' : 'ERR'}
								</span>
								<span className="wpgraphql-ide-history-doc-label">
									{entry.docLabel}
								</span>
								<span className="wpgraphql-ide-history-duration">
									{entry.duration_ms}ms
								</span>
							</div>
							<div className="wpgraphql-ide-history-entry-time">
								{dateI18n('M j, g:i A', entry.timestamp * 1000)}
							</div>
							{entry.query && (
								<div className="wpgraphql-ide-history-entry-preview">
									{entry.query.slice(0, 100)}
									{entry.query.length > 100 ? '…' : ''}
								</div>
							)}
						</button>
					</li>
				))}
			</ul>
		</div>
	);
}
