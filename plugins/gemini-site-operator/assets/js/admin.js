/* global GSO */
( function () {
	'use strict';

	const strings = ( GSO && GSO.strings ) || {};

	function toast( message, type ) {
		let wrap = document.querySelector( '.gso-toasts' );
		if ( ! wrap ) {
			wrap = document.createElement( 'div' );
			wrap.className = 'gso-toasts';
			document.body.appendChild( wrap );
		}
		const item = document.createElement( 'div' );
		item.className = 'gso-toast gso-toast-' + ( type || 'info' );
		item.textContent = message;
		wrap.appendChild( item );
		setTimeout( function () {
			item.classList.add( 'is-out' );
			setTimeout( function () {
				item.remove();
			}, 350 );
		}, 5000 );
	}

	function request( task, data ) {
		const body = new FormData();
		body.append( 'action', 'gso_ajax' );
		body.append( 'nonce', GSO.nonce );
		body.append( 'task', task );
		Object.keys( data || {} ).forEach( function ( key ) {
			const value = data[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( entry ) {
					body.append( key + '[]', entry );
				} );
			} else if ( value !== undefined && value !== null ) {
				body.append( key, value );
			}
		} );

		return fetch( GSO.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					const message = json && json.data && json.data.message ? json.data.message : strings.error || 'خطا';
					throw new Error( message );
				}
				return json.data;
			} );
	}

	function busy( button, on ) {
		if ( ! button ) {
			return;
		}
		if ( on ) {
			button.dataset.original = button.textContent;
			button.textContent = strings.working || '…';
			button.disabled = true;
		} else {
			button.textContent = button.dataset.original || button.textContent;
			button.disabled = false;
		}
	}

	function selectedIds() {
		return Array.from( document.querySelectorAll( '.gso-issue-check:checked' ) ).map( function ( input ) {
			return input.value;
		} );
	}

	function modal() {
		const element = document.getElementById( 'gso-modal' );
		if ( ! element ) {
			return null;
		}
		if ( ! element.dataset.bound ) {
			element.dataset.bound = '1';
			element.querySelector( '.gso-modal-close' ).addEventListener( 'click', function () {
				element.hidden = true;
			} );
			element.addEventListener( 'click', function ( event ) {
				if ( event.target === element ) {
					element.hidden = true;
				}
			} );
			document.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Escape' ) {
					element.hidden = true;
				}
			} );
		}
		return element;
	}

	function escapeHtml( value ) {
		return String( value || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function showPreview( data ) {
		const element = modal();
		if ( ! element ) {
			toast( data.message, 'success' );
			return;
		}
		const preview = data.preview || {};
		const diff = preview.diff || { removed: [], added: [] };
		const removed = ( diff.removed || [] )
			.map( function ( line ) {
				return '<li class="gso-diff-removed">− ' + escapeHtml( line ) + '</li>';
			} )
			.join( '' );
		const added = ( diff.added || [] )
			.map( function ( line ) {
				return '<li class="gso-diff-added">+ ' + escapeHtml( line ) + '</li>';
			} )
			.join( '' );

		let snapshots = '';
		( data.snapshots || [] ).forEach( function ( snap ) {
			const used = snap.restored_at && snap.restored_at !== '0000-00-00 00:00:00';
			snapshots +=
				'<li>#' +
				escapeHtml( snap.id ) +
				' — ' +
				escapeHtml( snap.label ) +
				' <span class="gso-muted">' +
				escapeHtml( snap.created_at ) +
				'</span> ' +
				( used
					? '<span class="gso-muted">(بازگردانی‌شده)</span>'
					: '<button type="button" class="button button-small gso-danger" data-gso-task="rollback" data-snapshot="' + escapeHtml( snap.id ) + '">بازگردانی</button>' ) +
				'</li>';
		} );

		element.querySelector( '.gso-modal-body' ).innerHTML =
			'<p><strong>' + escapeHtml( data.title || '' ) + '</strong> <code>' + escapeHtml( preview.handler || '' ) + '</code></p>' +
			'<div class="gso-diff-grid">' +
			'<div><h3>قبل</h3><pre class="gso-pre">' + escapeHtml( preview.before || '—' ) + '</pre></div>' +
			'<div><h3>بعد</h3><pre class="gso-pre">' + escapeHtml( preview.after || '—' ) + '</pre></div>' +
			'</div>' +
			( removed || added ? '<h3>Diff</h3><ul class="gso-diff">' + removed + added + '</ul>' : '<p class="gso-muted">تغییر متنی قابل نمایش نیست.</p>' ) +
			( snapshots ? '<h3>نسخه‌های پشتیبان این مورد</h3><ul class="gso-snaps">' + snapshots + '</ul>' : '' );

		const actions = element.querySelector( '.gso-modal-actions' );
		actions.innerHTML = '';
		if ( preview.has_fix && data.issue ) {
			const apply = document.createElement( 'button' );
			apply.type = 'button';
			apply.className = 'button button-primary';
			apply.textContent = 'اعمال این اصلاح';
			apply.dataset.gsoTask = 'fix_apply';
			apply.dataset.issue = data.issue;
			apply.dataset.gsoConfirm = strings.confirm;
			actions.appendChild( apply );
		}
		element.hidden = false;
	}

	function setText( selector, value ) {
		const node = document.querySelector( selector );
		if ( node && value !== undefined && value !== null && value !== '' ) {
			node.textContent = value;
		}
	}

	const queueLabels = {
		idle: 'آماده',
		running: 'در حال اجرا',
		paused: 'متوقف‌شده',
		paused_quota: 'متوقف — سهمیه تمام شده',
		cancelled: 'لغوشده',
	};

	function updateScanUI( data ) {
		const state = data.state || {};
		const info = data.progress_info || {};
		const percent = typeof info.percent === 'number' ? info.percent : data.progress;
		const bar = document.querySelector( '.gso-progress span' );
		if ( bar && typeof percent === 'number' ) {
			bar.style.width = percent + '%';
		}
		const live = document.querySelector( '.gso-live-dot' );
		if ( live ) {
			live.classList.toggle( 'is-idle', ! state.active );
		}

		setText( '.gso-scan-phase', state.phase );
		setText( '.gso-scan-processed', info.processed !== undefined ? info.processed : state.processed );
		setText( '.gso-scan-total', info.total !== undefined ? info.total : state.total );
		setText( '.gso-scan-speed', info.speed !== undefined ? info.speed : 0 );
		setText( '.gso-scan-errors', info.errors !== undefined ? info.errors : state.errors );
		setText( '.gso-scan-eta', info.eta_minutes !== undefined ? info.eta_minutes : 0 );
		if ( data.last_log && data.last_log.message ) {
			setText( '.gso-scan-lastlog', 'آخرین رویداد: ' + data.last_log.message );
		}

		const queueState = data.queue_state || {};
		const status = queueState.status || 'idle';
		const badge = document.querySelector( '.gso-queue-status' );
		if ( badge ) {
			badge.dataset.status = status;
			badge.textContent = queueLabels[ status ] || status;
		}
		const queue = data.queue || {};
		setText( '.gso-q-pending', queue.pending );
		setText( '.gso-q-running', queue.running );
		setText( '.gso-q-failed', queue.failed );
		setText( '.gso-q-skipped', queue.skipped );

		const rate = data.rate || {};
		setText( '.gso-daily-used', rate.daily_used );
		setText( '.gso-daily-limit', rate.daily_limit > 0 ? rate.daily_limit : '∞' );

		const running = status === 'running' || status === 'idle';
		document.querySelectorAll( '[data-gso-task="queue_pause"], [data-gso-task="queue_run"]' ).forEach( function ( button ) {
			button.disabled = ! running;
		} );
		document.querySelectorAll( '[data-gso-task="queue_resume"]' ).forEach( function ( button ) {
			button.disabled = running;
		} );
		document.querySelectorAll( '[data-gso-task="queue_cancel"]' ).forEach( function ( button ) {
			button.disabled = status === 'cancelled';
		} );
	}

	let pollTimer = null;
	let wasActive = false;
	let reloaded = false;

	function poll() {
		if ( pollTimer ) {
			return;
		}
		pollTimer = setInterval( function () {
			request( 'scan_status', {} )
				.then( function ( data ) {
					updateScanUI( data );
					const active = !!( data.state && data.state.active );
					if ( wasActive && ! active && ! reloaded ) {
						reloaded = true;
						setTimeout( function () {
							window.location.reload();
						}, 1200 );
					}
					wasActive = active;
				} )
				.catch( function () {
					clearInterval( pollTimer );
					pollTimer = null;
				} );
		}, 5000 );
	}

	function handleTask( button ) {
		const task = button.dataset.gsoTask;
		const issue = button.dataset.issue;
		const snapshot = button.dataset.snapshot;
		const data = {};

		if ( task === 'bulk_fix' || task === 'bulk_enqueue' ) {
			const ids = selectedIds();
			if ( ! ids.length ) {
				toast( 'هیچ موردی انتخاب نشده است.', 'error' );
				return;
			}
			data.ids = ids;
		}
		if ( task === 'bulk_filter' ) {
			data.severity = button.dataset.severity || '';
			data.status = button.dataset.status || 'open_all';
		}
		if ( issue ) {
			data.issue_id = issue;
		}
		if ( snapshot ) {
			data.snapshot_id = snapshot;
		}
		if ( task === 'queue_retry_item' ) {
			data.queue_id = button.dataset.queue;
		}
		if ( task === 'fix_apply' || task === 'rollback' ) {
			if ( ! window.confirm( button.dataset.gsoConfirm || strings.confirm || 'ادامه؟' ) ) {
				return;
			}
		}

		busy( button, true );
		request( task, data )
			.then( function ( response ) {
				busy( button, false );
				toast( response.message || strings.done, 'success' );
				if ( task === 'scan_start' ) {
					updateScanUI( response );
					poll();
					return;
				}
				if ( task === 'fix_preview' || task === 'fix_prepare' ) {
					if ( response.preview ) {
						showPreview( Object.assign( { issue: issue, message: response.message }, response ) );
					}
					if ( task === 'fix_prepare' ) {
						setTimeout( function () {
							window.location.reload();
						}, 2500 );
					}
					return;
				}
				if ( [ 'fix_apply', 'issue_ignore', 'issue_reopen', 'rollback', 'bulk_fix', 'bulk_filter', 'queue_pause', 'queue_resume', 'queue_cancel', 'queue_retry', 'queue_retry_item' ].indexOf( task ) !== -1 ) {
					setTimeout( function () {
						window.location.reload();
					}, 900 );
				}
			} )
			.catch( function ( error ) {
				busy( button, false );
				toast( error.message, 'error' );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '[data-gso-task]' );
		if ( button ) {
			event.preventDefault();
			handleTask( button );
			return;
		}
		const confirmButton = event.target.closest( 'button[data-gso-confirm]' );
		if ( confirmButton && ! window.confirm( confirmButton.dataset.gsoConfirm || 'ادامه؟' ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		const form = event.target.closest( '[data-gso-confirm]' );
		if ( form && ! window.confirm( form.dataset.gsoConfirm ) ) {
			event.preventDefault();
			return;
		}
		const ajaxForm = event.target.closest( '[data-gso-ajax-form]' );
		if ( ajaxForm ) {
			event.preventDefault();
			const submit = ajaxForm.querySelector( 'button[type="submit"]' );
			const postId = ajaxForm.querySelector( '[name="post_id"]' );
			busy( submit, true );
			request( ajaxForm.dataset.gsoAjaxForm, { post_id: postId ? postId.value : 0 } )
				.then( function ( response ) {
					busy( submit, false );
					toast( response.message, 'success' );
					const box = ajaxForm.parentElement.querySelector( '.gso-ajax-result' );
					if ( box ) {
						box.textContent = response.message;
					}
				} )
				.catch( function ( error ) {
					busy( submit, false );
					toast( error.message, 'error' );
				} );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if ( event.target && event.target.id === 'gso-select-all' ) {
			document.querySelectorAll( '.gso-issue-check' ).forEach( function ( input ) {
				input.checked = event.target.checked;
			} );
		}
		if ( event.target && event.target.id === 'gso-css-toggle' ) {
			const area = document.querySelector( '[name="css"]' );
			if ( area ) {
				area.readOnly = ! event.target.checked;
			}
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( document.querySelector( '.gso-progress' ) || document.querySelector( '.gso-queue-status' ) ) {
			request( 'scan_status', {} )
				.then( function ( data ) {
					updateScanUI( data );
					wasActive = !!( data.state && data.state.active );
					poll();
				} )
				.catch( function () {} );
		}
		document.querySelectorAll( 'textarea[readonly]' ).forEach( function ( area ) {
			area.addEventListener( 'click', function () {
				area.select();
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( area.value ).then( function () {
						toast( strings.copied || 'کپی شد', 'info' );
					} );
				}
			} );
		} );
	} );
} )();
