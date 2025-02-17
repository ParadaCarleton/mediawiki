<template>
	<cdx-accordion
		:class="`mw-block-log mw-block-log__type-${ blockLogType }`"
	>
		<template #title>
			{{ title }}
			<cdx-info-chip :icon="infoChipIcon" :status="infoChipStatus">
				{{ logEntriesCount }}
			</cdx-info-chip>
		</template>
		<cdx-table
			:caption="title"
			:columns="!!logEntries.length ? columns : []"
			:data="logEntries"
			:use-row-headers="true"
			:hide-caption="true"
		>
			<template #empty-state>
				{{ emptyState }}
			</template>
			<template #item-timestamp="{ item }">
				<a
					v-if="item.logid"
					:href="mw.util.getUrl( 'Special:Log', { logid: item.logid } )"
				>
					{{ util.formatTimestamp( item.timestamp ) }}
				</a>
				<span v-else>
					{{ util.formatTimestamp( item.timestamp ) }}
				</span>
			</template>
			<template v-if="blockLogType !== 'active'" #item-type="{ item }">
				{{ util.getBlockActionMessage( item ) }}
			</template>
			<template v-if="blockLogType === 'active'" #item-target="{ item }">
				<!-- eslint-disable-next-line vue/no-v-html -->
				<span v-html="$i18n( 'userlink-with-contribs', item ).parse()"></span>
			</template>
			<template #item-expiry="{ item }">
				<span v-if="item.expires">
					{{ util.formatTimestamp( item.expires, item.duration ) }}
				</span>
				<span v-else class="mw-block-log-nodata">
					—
				</span>
			</template>
			<template #item-blockedby="{ item }">
				<!-- eslint-disable-next-line vue/no-v-html -->
				<span v-html="$i18n( 'userlink-with-contribs', item ).parse()"></span>
			</template>
			<template #item-parameters="{ item }">
				<div v-if="!item" class="mw-block-log-params mw-block-log-nodata">
					—
				</div>
				<ul v-else>
					<li v-for="( parameter, index ) in item" :key="index">
						{{ util.getBlockFlagMessage( parameter ) }}
					</li>
				</ul>
			</template>
			<template #item-reason="{ item }">
				<div
					v-if="!item"
					class="mw-block-log-nodata"
					:aria-label="$i18n( 'block-user-no-reason-given-aria-details' ).text()"
				>
					{{ $i18n( 'block-user-no-reason-given' ).text() }}
				</div>
				<span v-else>
					{{ item }}
				</span>
			</template>
			<template v-if="blockLogType === 'active'" #item-modify>
				<!-- TODO: Ensure dropdown menu uses Right-Top layout (https://w.wiki/BTaj) -->
				<cdx-menu-button
					v-model:selected="selection"
					:menu-items="menuItems"
					class="mw-block-active-blocks__menu"
					aria-label="Modify block"
					type="button"
				>
					<cdx-icon :icon="cdxIconEllipsis"></cdx-icon>
				</cdx-menu-button>
			</template>
		</cdx-table>
		<div v-if="moreBlocks" class="mw-block-log-fulllog">
			<a
				:href="mw.util.getUrl( 'Special:Log', { page: 'User:' + targetUser, type: blockLogType === 'suppress' ? 'suppress' : 'block' } )"
			>
				{{ $i18n( 'log-fulllog' ).text() }}
			</a>
		</div>
	</cdx-accordion>
</template>

<script>
const util = require( '../util.js' );
const { computed, defineComponent, ref, watch } = require( 'vue' );
const { CdxAccordion, CdxTable, CdxMenuButton, CdxIcon, CdxInfoChip } = require( '@wikimedia/codex' );
const { storeToRefs } = require( 'pinia' );
const useBlockStore = require( '../stores/block.js' );
const { cdxIconEllipsis, cdxIconEdit, cdxIconTrash, cdxIconClock, cdxIconAlert } = require( '../icons.json' );

module.exports = exports = defineComponent( {
	name: 'BlockLog',
	components: { CdxAccordion, CdxTable, CdxMenuButton, CdxIcon, CdxInfoChip },
	props: {
		blockLogType: {
			type: String,
			default: 'recent'
		}
	},
	setup( props ) {
		const store = useBlockStore();
		const { targetUser } = storeToRefs( store );

		let title = mw.message( 'block-user-previous-blocks' ).text();
		let emptyState = mw.message( 'block-user-no-previous-blocks' ).text();
		if ( props.blockLogType === 'active' ) {
			title = mw.message( 'block-user-active-blocks' ).text();
			emptyState = mw.message( 'block-user-no-active-blocks' ).text();
		} else if ( props.blockLogType === 'suppress' ) {
			title = mw.message( 'block-user-suppressed-blocks' ).text();
			emptyState = mw.message( 'block-user-no-suppressed-blocks' ).text();
		}

		const columns = [
			{ id: 'timestamp', label: mw.message( 'blocklist-timestamp' ).text(), minWidth: '112px' },
			props.blockLogType === 'recent' || props.blockLogType === 'suppress' ?
				{ id: 'type', label: mw.message( 'blocklist-type-header' ).text(), minWidth: '112px' } :
				{ id: 'target', label: mw.message( 'blocklist-target' ).text(), minWidth: '200px' },
			{ id: 'expiry', label: mw.message( 'blocklist-expiry' ).text(), minWidth: '112px' },
			{ id: 'blockedby', label: mw.message( 'blocklist-by' ).text(), minWidth: '200px' },
			{ id: 'parameters', label: mw.message( 'blocklist-params' ).text(), minWidth: '160px' },
			{ id: 'reason', label: mw.message( 'blocklist-reason' ).text(), minWidth: '160px' },
			...( props.blockLogType === 'active' ?
				[ { id: 'modify', label: '', minWidth: '100px' } ] : [] )
		];
		const menuItems = [
			{ label: mw.message( 'block-item-edit' ).text(), value: 'edit', url: mw.util.getUrl( 'Special:Block/' + targetUser.value ), icon: cdxIconEdit },
			{ label: mw.message( 'block-item-remove' ).text(), value: 'remove', url: mw.util.getUrl( 'Special:Unblock/' + targetUser.value ), icon: cdxIconTrash }
		];
		const selection = ref( null );

		const logEntries = ref( [] );
		const moreBlocks = ref( false );
		const FETCH_LIMIT = 10;

		const logEntriesCount = computed( () => {
			if ( moreBlocks.value ) {
				return mw.msg(
					'block-user-label-count-exceeds-limit',
					mw.language.convertNumber( FETCH_LIMIT )
				);
			}
			return mw.language.convertNumber( logEntries.value.length );
		} );

		const infoChipIcon = computed( () => props.blockLogType === 'active' ? cdxIconAlert : cdxIconClock );
		const infoChipStatus = computed( () => logEntries.value.length > 0 && props.blockLogType === 'active' ? 'warning' : 'notice' );

		/**
		 * Construct the data object needed for a template row, from a logentry API response.
		 *
		 * @param {Array} logevents
		 * @return {Array}
		 */
		function logentriesToRows( logevents ) {
			const rows = [];
			for ( let i = 0; i < logevents.length; i++ ) {
				const logevent = logevents[ i ];
				rows.push( {
					timestamp: {
						timestamp: logevent.timestamp,
						logid: logevent.logid
					},
					type: logevent.action,
					expiry: {
						expires: logevent.params.expiry,
						duration: logevent.params.duration,
						type: logevent.action
					},
					blockedby: logevent.user,
					parameters: logevent.params.flags,
					reason: logevent.comment
				} );
			}
			return rows;
		}

		watch( targetUser, ( newValue ) => {
			if ( newValue ) {
				// Update the URLs for the menu items
				menuItems[ 0 ].url = mw.util.getUrl( 'Special:Block/' + newValue );
				menuItems[ 1 ].url = mw.util.getUrl( 'Special:Unblock/' + newValue );
				store.getBlockLogData( props.blockLogType ).then( ( responses ) => {
					let newData = [];
					const data = responses[ 0 ].query;

					if ( props.blockLogType === 'recent' ) {
						// List of recent block entries.
						newData = logentriesToRows( data.logevents );
						moreBlocks.value = newData.length >= FETCH_LIMIT;

					} else if ( props.blockLogType === 'suppress' ) {
						// List of suppress/block or suppress/reblock log entries.
						newData.push( ...logentriesToRows( data.logevents ) );
						newData.push( ...logentriesToRows( responses[ 1 ].query.logevents ) );
						newData.sort( ( a, b ) => b.timestamp.logid - a.timestamp.logid );
						moreBlocks.value = newData.length >= FETCH_LIMIT;
						// Re-apply limit, as each may have been longer.
						newData = newData.slice( 0, FETCH_LIMIT );

					} else {
						// List of active blocks.
						for ( let i = 0; i < data.blocks.length; i++ ) {
							newData.push( {
								timestamp: {
									timestamp: data.blocks[ i ].timestamp
								},
								target: data.blocks[ i ].user,
								expiry: {
									expires: data.blocks[ i ].expiry,
									duration: data.blocks[ i ].expiry === 'infinity' ? 'infinity' : null
								},
								blockedby: data.blocks[ i ].by,
								parameters:
									[
										data.blocks[ i ].anononly ? 'anononly' : null,
										data.blocks[ i ].nocreate ? 'nocreate' : null,
										data.blocks[ i ].autoblock ? null : 'noautoblock',
										data.blocks[ i ].noemail ? 'noemail' : null,
										data.blocks[ i ].allowusertalk ? null : 'nousertalk',
										data.blocks[ i ].hidden ? 'hiddenname' : null
									].filter( ( e ) => e !== null ),
								reason: data.blocks[ i ].reason
							} );
						}
					}

					logEntries.value = newData;
				} );
			} else {
				moreBlocks.value = false;
				logEntries.value = [];
			}
		}, { immediate: true } );

		return {
			mw,
			util,
			title,
			emptyState,
			columns,
			menuItems,
			selection,
			cdxIconEllipsis,
			logEntries,
			moreBlocks,
			targetUser,
			logEntriesCount,
			infoChipIcon,
			infoChipStatus
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mw-block-log {
	word-break: auto-phrase;

	// Override Codex's overflow:auto for the table, so the MenuButton displays correctly.
	// @todo Remove after T379947 is resolved.
	.cdx-table__table-wrapper {
		overflow: unset;
	}
}

.mw-block-log-nodata {
	color: @color-subtle;
	font-style: italic;

	&.mw-block-log-params {
		padding-left: @spacing-75;
	}
}

.mw-block-log-fulllog {
	margin-top: @spacing-50;
}

.mw-block-active-blocks__menu {
	text-align: center;
}
</style>
