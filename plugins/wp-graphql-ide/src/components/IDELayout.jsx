import React, { useState, useCallback, useEffect, useRef } from 'react';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	ResizableBox,
	TabPanel,
	Spinner,
	Tooltip,
} from '@wordpress/components';
import {
	Icon,
	update,
	moreVertical,
	close,
	settings,
	sidebar,
	edit,
	search,
	help,
	backup,
} from '@wordpress/icons';
import { useDispatch, useSelect } from '@wordpress/data';
import { GraphQLEditor } from './editors/GraphQLEditor';
import { JSONEditor } from './editors/JSONEditor';
import { ResponseViewer } from './editors/ResponseViewer';
import { EditorToolbar } from './EditorToolbar';
import { DocumentTabs } from './DocumentTabs';
import ActivityPanel from './ActivityPanel';
import { ErrorsPanel } from './ErrorsPanel';
import { HeadersPanel } from './HeadersPanel';
import { ResponseTableView } from './ResponseTableView';

import authStyles from '../../styles/ToggleAuthenticationButton.module.css';
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';

const AUTOSAVE_DELAY = 2000;
const MAX_HISTORY_ENTRIES = 50;

const PANEL_ICONS = {
	'docs-explorer': search,
	help,
	history: backup,
};

function extractOperationName(query) {
	if (!query) {
		return null;
	}
	const match = query.match(/(?:query|mutation|subscription)\s+(\w+)/);
	return match ? match[1] : null;
}

/**
 * Main IDE layout component.
 *
 * Composes the CodeMirror 6 editors with @wordpress/components to provide a
 * native WordPress admin look and feel. State is managed via the
 * `wpgraphql-ide/app` @wordpress/data store.
 *
 * @param {Object}   props
 * @param {Function} props.fetcher   - GraphQL fetcher function.
 * @param {Function} [props.onClose] - Optional close handler for drawer mode.
 */
export function IDELayout({ fetcher, onClose }) {
	const query = useSelect(
		(select) => select('wpgraphql-ide/app').getQuery() || '',
		[]
	);
	const variables = useSelect(
		(select) => select('wpgraphql-ide/app').getVariables(),
		[]
	);
	const headers = useSelect(
		(select) => select('wpgraphql-ide/app').getHeaders(),
		[]
	);
	const response = useSelect(
		(select) => select('wpgraphql-ide/app').getResponse(),
		[]
	);
	const responseHeaders = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseHeaders(),
		[]
	);
	const responseStatus = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseStatus(),
		[]
	);
	const responseDuration = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseDuration(),
		[]
	);
	const responseSize = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseSize(),
		[]
	);

	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);
	const allDocuments = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getDocuments(),
		[]
	);
	const openTabs = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getOpenTabs(),
		[]
	);

	const isAuthenticated = useSelect(
		(select) => select('wpgraphql-ide/app').isAuthenticated(),
		[]
	);

	const {
		setQuery,
		setVariables,
		setHeaders,
		setResponse,
		setResponseHeaders,
		setResponseMeta,
		toggleAuthentication,
	} = useDispatch('wpgraphql-ide/app');

	const { loadDocuments, saveDocument, createTab, switchTab, closeTab } =
		useDispatch('wpgraphql-ide/document-editor');

	const { schema, isLoading: isSchemaLoading, refetch } = useSchema(fetcher);

	const activeDocRef = useRef(null);
	activeDocRef.current = activeDocument;

	// Capture the document ID and query when execution starts, so the
	// result goes to the correct document even if the user switches tabs.
	const executingDocIdRef = useRef(null);
	const executingQueryRef = useRef(null);
	const executingHeadersRef = useRef(null);

	const handleExecutionComplete = useCallback(
		({
			result,
			duration_ms: duration,
			status: execStatus,
			variables: vars,
		}) => {
			const docId = executingDocIdRef.current;
			if (!docId) {
				return;
			}

			const responseStr = JSON.stringify(result, null, 2);
			const entry = {
				timestamp: Math.floor(Date.now() / 1000),
				query: executingQueryRef.current || '',
				variables: vars || '',
				headers: executingHeadersRef.current || '',
				duration_ms: duration,
				status: execStatus,
			};

			// Dispatch a thunk that reads the latest history from the store
			// to avoid stale closure issues.
			const { select: sel, dispatch: dis } =
				// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
				require('@wordpress/data');
			const latestDoc = sel('wpgraphql-ide/document-editor').getDocument(
				docId
			);
			const currentHistory = latestDoc?.history || [];
			const updated = [...currentHistory, entry].slice(
				-MAX_HISTORY_ENTRIES
			);

			dis('wpgraphql-ide/document-editor').saveDocument(docId, {
				history: updated,
			});
			dis('wpgraphql-ide/document-editor').setDocumentResponse(
				docId,
				responseStr
			);
		},
		[]
	);

	const executionOptions = useRef({ onComplete: handleExecutionComplete });
	executionOptions.current.onComplete = handleExecutionComplete;

	const { isFetching, run, stop } = useExecution(
		fetcher,
		executionOptions.current
	);

	const savedQueryWidth =
		window.localStorage.getItem('wpgraphql_ide_query_width') || '50%';
	const savedEditorHeight =
		window.localStorage.getItem('wpgraphql_ide_editor_height') || '70%';
	const savedResponseViewerHeight =
		window.localStorage.getItem('wpgraphql_ide_response_viewer_height') ||
		'70%';
	const [queryPaneWidth, setQueryPaneWidth] = useState(savedQueryWidth);
	const [editorHeight, setEditorHeight] = useState(savedEditorHeight);
	const [responseViewerHeight, setResponseViewerHeight] = useState(
		savedResponseViewerHeight
	);
	const [isLoaded, setIsLoaded] = useState(false);

	// Track pane body heights so we can cap the inner ResizableBox and
	// prevent dragging to the point where the bottom tab panel disappears.
	const queryPaneBodyRef = useRef(null);
	const [queryPaneBodyH, setQueryPaneBodyH] = useState(0);
	const responsePaneBodyRef = useRef(null);
	const [responsePaneBodyH, setResponsePaneBodyH] = useState(0);
	useEffect(() => {
		const qEl = queryPaneBodyRef.current;
		const rEl = responsePaneBodyRef.current;
		if (!qEl && !rEl) {
			return;
		}
		const ro = new window.ResizeObserver(() => {
			if (qEl) {
				setQueryPaneBodyH(qEl.offsetHeight);
			}
			if (rEl) {
				setResponsePaneBodyH(rEl.offsetHeight);
			}
		});
		if (qEl) {
			ro.observe(qEl);
		}
		if (rEl) {
			ro.observe(rEl);
		}
		return () => ro.disconnect();
	}, []);
	const saveTimerRef = useRef(null);

	// ESC key closes the drawer when in drawer mode.
	useEffect(() => {
		if (!onClose) {
			return;
		}
		const handleKeyDown = (e) => {
			if (e.key === 'Escape') {
				onClose();
			}
		};
		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [onClose]);

	// Load documents after mount.
	useEffect(() => {
		loadDocuments().then(() => setIsLoaded(true));
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// When active document changes, populate editors and restore response.
	useEffect(() => {
		if (!activeDocument) {
			return;
		}
		setQuery(activeDocument.query || '');
		setVariables(activeDocument.variables || '');
		setHeaders(activeDocument.headers || '');
		setResponse(activeDocument.lastResponse || '');
		// Response metadata (headers, status, duration, size) isn't persisted
		// per-document — clear on switch so nothing is stale.
		setResponseHeaders(null);
		setResponseMeta({});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [activeDocument?.id]);

	// Create a default tab if loaded but no tabs exist.
	useEffect(() => {
		if (isLoaded && !activeDocument) {
			createTab('Untitled');
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [isLoaded, activeDocument]);

	// Debounced auto-save.
	const scheduleAutoSave = useCallback(
		(field, value) => {
			if (!activeDocument) {
				return;
			}
			if (saveTimerRef.current) {
				clearTimeout(saveTimerRef.current);
			}
			saveTimerRef.current = setTimeout(() => {
				saveDocument(activeDocument.id, { [field]: value });
			}, AUTOSAVE_DELAY);
		},
		[activeDocument, saveDocument]
	);

	const handleQueryChange = useCallback(
		(value) => {
			setQuery(value);
			scheduleAutoSave('query', value);
		},
		[setQuery, scheduleAutoSave]
	);

	const handleVariablesChange = useCallback(
		(value) => {
			setVariables(value);
			scheduleAutoSave('variables', value);
		},
		[setVariables, scheduleAutoSave]
	);

	const handleHeadersChange = useCallback(
		(value) => {
			setHeaders(value);
			scheduleAutoSave('headers', value);
		},
		[setHeaders, scheduleAutoSave]
	);

	const executeQueryRef = useRef(null);
	executeQueryRef.current = () => {
		if (isFetching) {
			stop();
		} else {
			// Load schema on first execution if not loaded yet.
			if (!schema) {
				refetch();
			}
			// Capture execution context for the correct document.
			executingDocIdRef.current = activeDocument?.id || null;
			executingQueryRef.current = query;
			executingHeadersRef.current = headers;
			run();
		}
	};

	const executeQuery = () => executeQueryRef.current();

	const { prettifyQuery } = useDispatch('wpgraphql-ide/app');
	const prettifyRef = useRef(null);
	prettifyRef.current = () => {
		if (query) {
			prettifyQuery(query);
		}
	};

	const editorKeyBindings = useRef([
		{
			key: 'Mod-Enter',
			run: () => {
				executeQueryRef.current();
				return true;
			},
		},
		{
			key: 'Ctrl-Shift-p',
			run: () => {
				prettifyRef.current();
				return true;
			},
		},
	]);

	const panels = useSelect(
		(select) => select('wpgraphql-ide/activity-bar').activityPanels(),
		[]
	);
	const visiblePanel = useSelect(
		(select) => select('wpgraphql-ide/activity-bar').visiblePanel(),
		[]
	);
	const { toggleActivityPanelVisibility, setVisiblePanel } = useDispatch(
		'wpgraphql-ide/activity-bar'
	);

	// Remember the last open panel so the sidebar toggle can restore it.
	const lastPanelRef = useRef(null);
	useEffect(() => {
		if (visiblePanel) {
			lastPanelRef.current = visiblePanel.name;
		}
	}, [visiblePanel]);

	const handleSidebarToggle = () => {
		if (visiblePanel) {
			toggleActivityPanelVisibility(visiblePanel.name);
		} else {
			const target = lastPanelRef.current || navPanels[0]?.name;
			if (target) {
				setVisiblePanel(target);
			}
		}
	};

	// Query composer inline toggle — persisted across sessions
	const [showQueryComposer, setShowQueryComposer] = useState(() => {
		try {
			return (
				window.localStorage.getItem(
					'wpgraphql_ide_show_query_composer'
				) === 'true'
			);
		} catch {
			return false;
		}
	});

	const toggleQueryComposer = () => {
		setShowQueryComposer((prev) => {
			const next = !prev;
			try {
				window.localStorage.setItem(
					'wpgraphql_ide_show_query_composer',
					String(next)
				);
			} catch {
				// ignore
			}
			return next;
		});
	};

	// query-composer is document-specific — lives in the editor area, not the global activity bar
	const navPanels = panels.filter(
		(p) => p.name !== 'documents' && p.name !== 'query-composer'
	);
	const queryComposerPanel = panels.find((p) => p.name === 'query-composer');

	// Settings URL — links to WPGraphQL general settings in WP admin.
	const settingsUrl =
		typeof window !== 'undefined' && window.wpApiSettings?.root
			? window.location.origin +
				'/wp-admin/admin.php?page=graphql-settings'
			: '/wp-admin/admin.php?page=graphql-settings';

	const ComposerContent = queryComposerPanel?.content || null;

	// Response meta formatting helpers for the header display.
	const formatDurationMs = (ms) => {
		if (typeof ms !== 'number' || !isFinite(ms)) {
			return null;
		}
		if (ms >= 1000) {
			return `${(ms / 1000).toFixed(2)}s`;
		}
		return `${ms.toFixed(0)}ms`;
	};
	const formatSize = (bytes) => {
		if (typeof bytes !== 'number' || !isFinite(bytes)) {
			return null;
		}
		if (bytes < 1024) {
			return `${bytes} B`;
		}
		if (bytes < 1024 * 1024) {
			return `${(bytes / 1024).toFixed(1)} KB`;
		}
		return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
	};
	const statusClass = (s) => {
		if (typeof s !== 'number') {
			return '';
		}
		if (s >= 500) {
			return ' is-error';
		}
		if (s >= 400) {
			return ' is-warning';
		}
		if (s >= 200 && s < 300) {
			return ' is-success';
		}
		return '';
	};

	// Parse the raw response once; reuse for the logical-parts breakdown below
	// and for the full-tree Table view.
	const parsedResponseRoot = React.useMemo(() => {
		if (!response) {
			return null;
		}
		try {
			return JSON.parse(response);
		} catch {
			return null;
		}
	}, [response]);
	const parsedResponse = React.useMemo(() => {
		const p = parsedResponseRoot;
		return {
			data: p?.data ?? null,
			errors: Array.isArray(p?.errors) ? p.errors : null,
			extensions: p?.extensions ?? null,
		};
	}, [parsedResponseRoot]);
	const responseExtensions = parsedResponse.extensions;
	const responseErrors = parsedResponse.errors;

	// Stringified `data` portion for the default viewer in formatted mode.
	const responseDataString = React.useMemo(() => {
		if (parsedResponse.data === null || parsedResponse.data === undefined) {
			return '';
		}
		return JSON.stringify(parsedResponse.data, null, 2);
	}, [parsedResponse.data]);

	// Response view mode: 'formatted' | 'table' | 'raw' — persisted.
	const [responseViewMode, setResponseViewMode] = useState(() => {
		try {
			const stored = window.localStorage.getItem(
				'wpgraphql_ide_response_view_mode'
			);
			return stored === 'raw' || stored === 'table'
				? stored
				: 'formatted';
		} catch {
			return 'formatted';
		}
	});

	const changeResponseViewMode = (mode) => {
		setResponseViewMode(mode);
		try {
			window.localStorage.setItem(
				'wpgraphql_ide_response_view_mode',
				mode
			);
		} catch {
			// ignore
		}
	};

	// Cap editor/response viewer heights to leave room for the bottom tab panels.
	const TOOLBAR_H = 40;
	const MIN_BOTTOM_PANEL_H = 80;
	const maxEditorHeight =
		queryPaneBodyH > TOOLBAR_H + MIN_BOTTOM_PANEL_H + 50
			? queryPaneBodyH - TOOLBAR_H - MIN_BOTTOM_PANEL_H
			: undefined;
	const maxResponseViewerHeight =
		responsePaneBodyH > MIN_BOTTOM_PANEL_H + 50
			? responsePaneBodyH - MIN_BOTTOM_PANEL_H
			: undefined;

	// Response extension tabs are registered via `registerResponseExtensionTab`.
	// Only tabs whose key appears in the current response's `extensions` object
	// are shown; their `content` callback receives `{ data, response }`.
	const registeredExtensionTabs = useSelect(
		(select) => select('wpgraphql-ide/response-extensions').extensionTabs(),
		[]
	);
	const activeExtensionTabs = registeredExtensionTabs.filter(
		(tab) => responseExtensions && tab.name in responseExtensions
	);

	// Counts for root tab badges.
	const errorsCount = Array.isArray(responseErrors)
		? responseErrors.length
		: 0;
	const extensionsCount = activeExtensionTabs.length;
	const headersCount =
		responseHeaders && typeof responseHeaders === 'object'
			? Object.keys(responseHeaders).length
			: 0;

	const responseRootTabs = [
		{
			name: 'headers',
			title: `Headers (${headersCount})`,
		},
		{
			name: 'errors',
			title: `Errors (${errorsCount})`,
		},
		{
			name: 'extensions',
			title: `Extensions (${extensionsCount})`,
		},
	];

	return (
		<div className="wpgraphql-ide-container">
			{/* Global top bar */}
			<div className="wpgraphql-ide-topbar">
				<div className="wpgraphql-ide-topbar-left">
					<Tooltip
						text={
							visiblePanel ? 'Collapse sidebar' : 'Expand sidebar'
						}
					>
						<Button
							onClick={handleSidebarToggle}
							aria-label={
								visiblePanel
									? 'Collapse sidebar'
									: 'Expand sidebar'
							}
							size="compact"
							className={`wpgraphql-ide-topbar-btn${visiblePanel ? ' is-active' : ''}`}
						>
							<Icon icon={sidebar} />
						</Button>
					</Tooltip>
				</div>
				<div className="wpgraphql-ide-topbar-center">
					<span className="wpgraphql-ide-topbar-title">
						WPGraphQL
					</span>
				</div>
				<div className="wpgraphql-ide-topbar-right">
					<Tooltip text="Re-fetch schema">
						<Button
							onClick={refetch}
							disabled={isSchemaLoading}
							aria-label="Re-fetch schema"
							size="compact"
							className={`wpgraphql-ide-topbar-btn${isSchemaLoading ? ' is-loading' : ''}`}
						>
							<Icon icon={update} />
						</Button>
					</Tooltip>
					<Tooltip text="WPGraphQL Settings">
						<Button
							href={settingsUrl}
							aria-label="WPGraphQL Settings"
							size="compact"
							className="wpgraphql-ide-topbar-btn"
						>
							<Icon icon={settings} />
						</Button>
					</Tooltip>
					{onClose && (
						<>
							<div className="wpgraphql-ide-topbar-sep" />
							<Tooltip text="Close">
								<Button
									onClick={onClose}
									aria-label="Close"
									size="compact"
									className="wpgraphql-ide-topbar-btn"
								>
									<Icon icon={close} />
								</Button>
							</Tooltip>
						</>
					)}
				</div>
			</div>

			<div className="wpgraphql-ide-main">
				{/* Vertical activity bar — always visible, owns panel toggle buttons */}
				<div className="wpgraphql-ide-activity-bar">
					{navPanels.map((panel) => (
						<Tooltip key={panel.name} text={panel.title}>
							<Button
								onClick={() =>
									toggleActivityPanelVisibility(panel.name)
								}
								aria-label={panel.title}
								size="compact"
								className={`wpgraphql-ide-activity-btn${visiblePanel?.name === panel.name ? ' is-active' : ''}`}
							>
								<Icon icon={PANEL_ICONS[panel.name] ?? edit} />
							</Button>
						</Tooltip>
					))}
				</div>

				{/* Collapsible side panel — shows active panel content */}
				<ActivityPanel />

				{/* Editor area: tab bar connected to query + response */}
				<div className="wpgraphql-ide-editor-area">
					<div className="wpgraphql-ide-tab-bar">
						<DocumentTabs
							tabs={openTabs
								.map((tabId) =>
									allDocuments.find(
										(d) => String(d.id) === String(tabId)
									)
								)
								.filter(Boolean)
								.map((doc) => ({
									id: doc.id,
									title:
										extractOperationName(doc.query) ||
										doc.title ||
										'Untitled',
								}))}
							activeId={activeDocument?.id}
							onSwitch={(id) => switchTab(id)}
							onClose={(id) => closeTab(id)}
							onCreate={() => createTab()}
							onRename={(id, title) =>
								saveDocument(id, { title })
							}
						/>
					</div>

					<div className="wpgraphql-ide-editors">
						<ResizableBox
							size={{ width: queryPaneWidth, height: 'auto' }}
							minWidth={200}
							enable={{ right: true }}
							onResizeStop={(e, d, elt) => {
								const w = elt.offsetWidth;
								setQueryPaneWidth(w);
								window.localStorage.setItem(
									'wpgraphql_ide_query_width',
									String(w)
								);
							}}
							className="wpgraphql-ide-query-pane"
						>
							<div
								ref={queryPaneBodyRef}
								className="wpgraphql-ide-query-pane-body"
							>
								<div className="wpgraphql-ide-editor-toolbar">
									{ComposerContent && (
										<Tooltip
											text={
												showQueryComposer
													? 'Hide Query Composer'
													: 'Show Query Composer'
											}
										>
											<Button
												onClick={toggleQueryComposer}
												aria-label={
													showQueryComposer
														? 'Hide Query Composer'
														: 'Show Query Composer'
												}
												size="compact"
												className={`wpgraphql-ide-toolbar-composer-btn${showQueryComposer ? ' is-active' : ''}`}
											>
												<Icon icon={edit} />
											</Button>
										</Tooltip>
									)}
									<span className="wpgraphql-ide-editor-label">
										Query
									</span>
									<div className="wpgraphql-ide-editor-toolbar-spacer" />
									<div className="wpgraphql-ide-send-group">
										<span className="wpgraphql-ide-method-label">
											POST
										</span>
										<Tooltip
											text={
												isAuthenticated
													? 'Sending as logged-in user (click to switch)'
													: 'Sending as public user (click to switch)'
											}
										>
											<button
												type="button"
												onClick={toggleAuthentication}
												className={`wpgraphql-ide-auth-avatar ${!isAuthenticated ? authStyles.authAvatarPublic : ''}`}
												aria-label={
													isAuthenticated
														? 'Switch to public'
														: 'Switch to authenticated'
												}
											>
												<span
													className={
														authStyles.authAvatar
													}
													style={{
														backgroundImage: `url(${window.WPGRAPHQL_IDE_DATA?.context?.avatarUrl || ''})`,
													}}
												>
													<span
														className={
															authStyles.authBadge
														}
													/>
												</span>
											</button>
										</Tooltip>
										<Button
											variant="primary"
											onClick={executeQuery}
											disabled={isSchemaLoading}
											className="wpgraphql-ide-send-button"
											size="compact"
										>
											{isFetching ? 'Stop' : 'Send'}
										</Button>
									</div>
									<DropdownMenu
										icon={moreVertical}
										label="Editor actions"
									>
										{({ onClose: closeMenu }) => (
											<MenuGroup>
												<EditorToolbar
													onClose={closeMenu}
												/>
											</MenuGroup>
										)}
									</DropdownMenu>
								</div>
								<ResizableBox
									size={{
										width: '100%',
										height: editorHeight,
									}}
									minHeight={50}
									maxHeight={maxEditorHeight}
									enable={{ bottom: true }}
									onResizeStop={(e, d, elt) => {
										const h = elt.offsetHeight;
										setEditorHeight(h);
										window.localStorage.setItem(
											'wpgraphql_ide_editor_height',
											String(h)
										);
									}}
									className={`wpgraphql-ide-editor-resizable${showQueryComposer && ComposerContent ? ' has-composer' : ''}`}
								>
									{ComposerContent && showQueryComposer && (
										<div className="wpgraphql-ide-query-composer-inline">
											<ComposerContent />
										</div>
									)}
									<GraphQLEditor
										key={activeDocument?.id || 'empty'}
										value={query}
										onChange={handleQueryChange}
										schema={schema}
										extraKeys={editorKeyBindings.current}
									/>
								</ResizableBox>
								<TabPanel
									className="wpgraphql-ide-editor-tools"
									tabs={[
										{
											name: 'variables',
											title: 'Variables',
										},
										{ name: 'headers', title: 'Headers' },
									]}
								>
									{(tab) =>
										tab.name === 'variables' ? (
											<JSONEditor
												key="variables"
												value={variables}
												onChange={handleVariablesChange}
												placeholder="Variables (JSON)"
											/>
										) : (
											<JSONEditor
												key="headers"
												value={headers}
												onChange={handleHeadersChange}
												placeholder="Headers (JSON)"
											/>
										)
									}
								</TabPanel>
							</div>
							{/* end .wpgraphql-ide-query-pane-body */}
						</ResizableBox>

						<div className="wpgraphql-ide-response-pane">
							<div className="wpgraphql-ide-response-header">
								<span className="wpgraphql-ide-response-label">
									Response
								</span>
								{isFetching && <Spinner />}
								<div className="wpgraphql-ide-response-header-spacer" />
								{(responseStatus !== null ||
									responseDuration !== null ||
									responseSize !== null) && (
									<div className="wpgraphql-ide-response-meta">
										{responseStatus !== null && (
											<span
												className={`wpgraphql-ide-response-meta-status${statusClass(responseStatus)}`}
												title="HTTP status"
											>
												{responseStatus}
											</span>
										)}
										{responseDuration !== null && (
											<span
												className="wpgraphql-ide-response-meta-item"
												title="Duration"
											>
												{formatDurationMs(
													responseDuration
												)}
											</span>
										)}
										{responseSize !== null && (
											<span
												className="wpgraphql-ide-response-meta-item"
												title="Payload size"
											>
												{formatSize(responseSize)}
											</span>
										)}
									</div>
								)}
								<div
									className="wpgraphql-ide-response-mode-pill"
									role="radiogroup"
									aria-label="Response view mode"
								>
									{[
										{
											value: 'formatted',
											label: 'Formatted',
										},
										{ value: 'table', label: 'Table' },
										{ value: 'raw', label: 'Raw' },
									].map((opt) => (
										<button
											key={opt.value}
											type="button"
											role="radio"
											aria-checked={
												responseViewMode === opt.value
											}
											onClick={() =>
												changeResponseViewMode(
													opt.value
												)
											}
											className={`wpgraphql-ide-response-mode-pill-btn${responseViewMode === opt.value ? ' is-active' : ''}`}
										>
											{opt.label}
										</button>
									))}
								</div>
							</div>
							{responseViewMode === 'raw' && (
								<ResponseViewer value={response} />
							)}
							{responseViewMode === 'table' && (
								<ResponseTableView
									response={parsedResponseRoot}
								/>
							)}
							{responseViewMode === 'formatted' && (
								<div
									ref={responsePaneBodyRef}
									className="wpgraphql-ide-response-pane-body"
								>
									<ResizableBox
										size={{
											width: '100%',
											height: responseViewerHeight,
										}}
										minHeight={50}
										maxHeight={maxResponseViewerHeight}
										enable={{ bottom: true }}
										onResizeStop={(e, d, elt) => {
											const h = elt.offsetHeight;
											setResponseViewerHeight(h);
											window.localStorage.setItem(
												'wpgraphql_ide_response_viewer_height',
												String(h)
											);
										}}
										className="wpgraphql-ide-response-viewer-resizable"
									>
										<ResponseViewer
											value={responseDataString}
										/>
									</ResizableBox>
									<TabPanel
										className="wpgraphql-ide-response-tools"
										tabs={responseRootTabs}
									>
										{(tab) => {
											if (tab.name === 'headers') {
												return (
													<HeadersPanel
														headers={
															responseHeaders
														}
													/>
												);
											}
											if (tab.name === 'errors') {
												return (
													<ErrorsPanel
														errors={responseErrors}
													/>
												);
											}
											if (tab.name === 'extensions') {
												if (
													activeExtensionTabs.length ===
													0
												) {
													const hasUnregistered =
														responseExtensions &&
														Object.keys(
															responseExtensions
														).length > 0;
													return (
														<div className="wpgraphql-ide-extensions-panel">
															<p className="wpgraphql-ide-extensions-empty">
																{hasUnregistered
																	? 'The response contains extension data, but no extension has registered a tab to display it.'
																	: 'No extensions in the last response.'}
															</p>
														</div>
													);
												}
												return (
													<TabPanel
														className="wpgraphql-ide-extensions-subtabs"
														// Re-mount when the visible
														// sub-tabs change so the
														// active sub-tab resets.
														key={activeExtensionTabs
															.map((t) => t.name)
															.join('|')}
														tabs={activeExtensionTabs.map(
															(t) => ({
																name: t.name,
																title: t.title,
															})
														)}
													>
														{(subTab) => {
															const reg =
																registeredExtensionTabs.find(
																	(t) =>
																		t.name ===
																		subTab.name
																);
															const TabContent =
																reg?.content;
															if (!TabContent) {
																return null;
															}
															return (
																<div className="wpgraphql-ide-extensions-panel">
																	<TabContent
																		data={
																			responseExtensions?.[
																				subTab
																					.name
																			]
																		}
																		response={
																			response
																		}
																	/>
																</div>
															);
														}}
													</TabPanel>
												);
											}
											return null;
										}}
									</TabPanel>
								</div>
							)}
						</div>
					</div>
					{/* end .wpgraphql-ide-editors */}
				</div>
				{/* end .wpgraphql-ide-editor-area */}
			</div>
			{/* end .wpgraphql-ide-main */}
		</div>
	);
}
