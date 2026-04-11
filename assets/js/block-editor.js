/**
 * ScoreFix — Gutenberg document panel (issues for current post).
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var registerPlugin = wp.plugins.registerPlugin;
	var Spinner = wp.components.Spinner;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;

	var cfg = typeof scorefixEditor !== 'undefined' ? scorefixEditor : {};
	var i18n = cfg.i18n || {};

	function usePostIssues( postId ) {
		var state = useState( {
			loading: true,
			error: null,
			payload: null,
		} );
		var setState = state[ 1 ];

		useEffect(
			function () {
				if ( ! postId || postId < 1 ) {
					setState( {
						loading: false,
						error: null,
						payload: null,
					} );
					return;
				}
				setState( function ( s ) {
					return Object.assign( {}, s, { loading: true, error: null } );
				} );
				wp.apiFetch( {
					path: '/scorefix/v1/post/' + postId + '/issues',
				} )
					.then( function ( data ) {
						setState( {
							loading: false,
							error: null,
							payload: data,
						} );
					} )
					.catch( function () {
						setState( {
							loading: false,
							error: i18n.errorLoad || 'Error',
							payload: null,
						} );
					} );
			},
			[ postId ]
		);

		return state[ 0 ];
	}

	function ScoreFixPanel() {
		var postId = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var st = usePostIssues( postId );

		if ( ! postId ) {
			return null;
		}

		if ( st.loading ) {
			return el(
				PluginDocumentSettingPanel,
				{
					name: 'scorefix-issues',
					title: i18n.panelTitle || 'ScoreFix',
					className: 'scorefix-doc-panel',
				},
				el( Spinner, null )
			);
		}

		if ( st.error ) {
			return el(
				PluginDocumentSettingPanel,
				{
					name: 'scorefix-issues',
					title: i18n.panelTitle || 'ScoreFix',
					className: 'scorefix-doc-panel',
				},
				el( Notice, { status: 'error', isDismissible: false }, st.error )
			);
		}

		var p = st.payload;
		if ( ! p ) {
			return null;
		}

		var children = [];

		if ( ! p.scanned_at ) {
			children.push(
				el( Notice, { key: 'ns', status: 'warning', isDismissible: false }, i18n.noScan || '' )
			);
		} else if ( ! p.was_in_scan ) {
			children.push(
				el( Notice, { key: 'wi', status: 'info', isDismissible: false }, i18n.notInSample || '' )
			);
		}

		var issues = p.issues && p.issues.length ? p.issues : [];
		if ( issues.length ) {
			children.push(
				el(
					'ul',
					{ key: 'ul', className: 'scorefix-doc-panel__list' },
					issues.map( function ( iss, idx ) {
						var title = iss.title || iss.type || '';
						return el(
							'li',
							{ key: iss.id || idx, className: 'scorefix-doc-panel__item' },
							el( 'span', { className: 'scorefix-doc-panel__item-title' }, title ),
							el(
								'span',
								{ className: 'scorefix-doc-panel__meta' },
								( i18n.severity || 'Severity' ) +
									': ' +
									( iss.severity_label || '' ) +
									' · ' +
									( i18n.source || 'Source' ) +
									': ' +
									( iss.context_label || '' )
							)
						);
					} )
				)
			);
		} else if ( p.scanned_at && p.was_in_scan ) {
			children.push(
				el( Notice, { key: 'ok', status: 'success', isDismissible: false }, i18n.emptyIssues || '' )
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'scorefix-issues',
				title: i18n.panelTitle || 'ScoreFix',
				className: 'scorefix-doc-panel',
			},
			el( Fragment, null, children )
		);
	}

	registerPlugin( 'scorefix-document-issues', {
		icon: 'visibility',
		render: ScoreFixPanel,
	} );
} )( window.wp );
