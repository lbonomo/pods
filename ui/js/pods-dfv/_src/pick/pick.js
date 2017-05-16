/*global jQuery, _, Backbone, Marionette, wp, PodsI18n */
import template from '~/ui/js/pods-dfv/_src/pick/pick-layout.html';

import {PodsDFVFieldModel} from '~/ui/js/pods-dfv/_src/core/pods-field-model';
import {PodsDFVFieldLayout} from '~/ui/js/pods-dfv/_src/core/pods-field-views';

import {IframeFrame} from '~/ui/js/pods-dfv/_src/core/iframe-frame';

import {RelationshipCollection} from '~/ui/js/pods-dfv/_src/pick/relationship-model';
import {PickFieldModel} from '~/ui/js/pods-dfv/_src/pick/pick-field-model';

import {RadioView} from '~/ui/js/pods-dfv/_src/pick/views/radio-view';
import {CheckboxView} from '~/ui/js/pods-dfv/_src/pick/views/checkbox-view';
import {SelectView} from '~/ui/js/pods-dfv/_src/pick/views/select-view';
import {ListView} from '~/ui/js/pods-dfv/_src/pick/views/list-view';
import {AddNew} from '~/ui/js/pods-dfv/_src/pick/views/add-new';

const AJAX_ADD_NEW_ACTION = 'pods_relationship_popup';

const views = {
	'checkbox': CheckboxView,
	'select'  : SelectView,
	'select2' : SelectView,  // SelectView handles select2 as well
	'radio'   : RadioView,
	'list'    : ListView
};

let modalIFrame;

/**
 * @extends Backbone.View
 */
export const Pick = PodsDFVFieldLayout.extend( {
	template: _.template( template ),

	regions: {
		autocomplete: '.pods-ui-list-autocomplete',
		list        : '.pods-pick-values',
		addNew      : '.pods-ui-add-new'
	},

	/**
	 *
	 */
	onBeforeRender: function () {
		if ( this.collection === undefined ) {
			this.collection = new RelationshipCollection( this.fieldItemData );
		}
	},

	/**
	 *
	 */
	onRender: function () {
		this.fieldConfig = new PickFieldModel( this.model.get( 'fieldConfig' ) );

		// Autocomplete?
		if ( 'list' === this.fieldConfig.get( 'view_name' ) ) {
			this.showAutocomplete();
		}

		this.showList();

		// Add New?
		if ( '' !== this.fieldConfig.get( 'iframe_src' ) && 1 == this.fieldConfig.get( 'pick_allow_add_new' ) ) {
			this.showAddNew();
		}
	},

	/**
	 * This is for the List View's autocomplete for select from existing
	 */
	showAutocomplete: function () {
		let fieldConfig, model, collection, view;

		fieldConfig = {
			ajax_data         : this.fieldConfig.get( 'ajax_data' ),
			label             : this.fieldConfig.get( 'label' ),
			view_name         : 'select2',
			pick_format_type  : 'multi',
			selectFromExisting: true
		};

		model = new PodsDFVFieldModel( { fieldConfig: fieldConfig } );
		collection = this.collection.filterByUnselected();
		view = new SelectView( { collection: collection, fieldModel: model } );

		// Provide a custom list filter for the autocomplete portion's AJAX data lists
		view.filterAjaxList = this.filterAjaxList.bind( this );

		this.showChildView( 'autocomplete', view );
	},

	/**
	 *
	 */
	showList: function () {
		let viewName, View, list;

		// Setup the view to be used
		viewName = this.fieldConfig.get( 'view_name' );
		if ( views[ viewName ] === undefined ) {
			throw new Error( `Invalid view name "${viewName}"` );
		}
		View = views[ viewName ];
		list = new View( { collection: this.collection, fieldModel: this.model } );
		this.showChildView( 'list', list );
	},

	/**
	 *
	 */
	showAddNew: function () {
		let addNew = new AddNew( { fieldModel: this.model } );
		this.showChildView( 'addNew', addNew );
	},

	/**
	 *
	 */
	refreshAutocomplete: function ( newCollection ) {
		let autocomplete = this.getChildView( 'autocomplete' );
		autocomplete.collection = newCollection;
		autocomplete.render();
	},

	/**
	 * List Views need to filter items already selected from their select from existing list.  The AJAX function
	 * itself does not filter.
	 *
	 * @param data
	 */
	filterAjaxList: function( data ) {
		const selectedItems = this.collection.filterBySelected();
		const returnList = [];

		_.each( data.results, function ( element, index, list ) {
			if ( ! selectedItems.get( element.id ) ) {
				returnList.push( element );
			}
		} );

		return { 'results': returnList };
	},

	/**
	 * "Remove" in list view just toggles an item's selected attribute
	 *
	 * @param childView
	 */
	onChildviewRemoveItemClick: function ( childView ) {
		childView.model.toggleSelected();
		this.getChildView( 'list' ).render();

		// Keep autocomplete in sync, removed items should now be available choices
		if ( 'list' === this.fieldConfig.get( 'view_name' ) ) {
			this.refreshAutocomplete( this.collection.filterByUnselected() );
		}
	},

	/**
	 * @param childView
	 */
	onChildviewAddNewClick: function ( childView ) {
		const fieldConfig = this.model.get( 'fieldConfig' );

		modalIFrame = new IframeFrame( {
			title: fieldConfig.iframe_title_add,
			src  : fieldConfig.iframe_src
		} );

		jQuery( window ).on( 'dfv:modal:update', this.modalSuccess.bind( this ) );

		modalIFrame.modal.open();
	},

	/**
	 * @param childView
	 */
	onChildviewEditItemClick: function ( childView ) {
		const fieldConfig = this.model.get( 'fieldConfig' );

		modalIFrame = new IframeFrame( {
			title: fieldConfig.iframe_title_edit,
			src  : childView.ui.editButton.attr( 'href' )
		} );

		jQuery( window ).on( 'dfv:modal:update', this.modalSuccess.bind( this ) );

		modalIFrame.modal.open();
	},

	/**
	 *
	 * @param childView
	 */
	onChildviewChangeSelected: function ( childView ) {

		// Refresh the autocomplete and List View lists on autocomplete selection
		if ( childView.fieldConfig.selectFromExisting ) {
			this.refreshAutocomplete( this.collection.filterByUnselected() );
			this.getChildView( 'list' ).render();
		}
	},

	/**
	 * @param event
	 * @param data
	 */
	modalSuccess: function ( event, data ) {
		const itemModel = this.collection.get( data.id );

		if ( itemModel ) {
			// Edit: update an existing model and force a re-render
			itemModel.set( data );
			this.getChildView( 'list' ).render();
		}
		else {
			// Add new: create a new model in the collection
			this.collection.add( data );
		}

		modalIFrame.modal.close();
	}

} );