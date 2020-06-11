window.addEventListener( 'load', function () {
    var _list = document.querySelector( '.unlimited_page_sidebars_list' );
    if ( _list ) {
        _list.say_loading = function () {
            var _h = '';
            _h += '<li><p class="description -loading-list">';
            _h += unlimited_page_sidebars.loading_list;
            _h += '</p></li>';
            this.innerHTML = _h;
        }
        _list.retrieve_list = function () {
            var _list = this;
            if ( !_list.classList.contains( '-retrieving-data' ) ) {
                _list.say_loading();
                _list.classList.add( '-retrieving-data' )
                var _data = new URLSearchParams();
                _data.append( 'action', 'custom_sidebar_list' );
                fetch( ajaxurl, {
                    method: 'post',
                    body: _data
                } ).then( function ( _r ) {
                    _r.json().then( function ( _j ) {
                        if ( _j[ 'message' ] ) {
                            alert( _j[ 'message' ] );
                        } else {
                            var _emp = document.querySelector( '.unlimited_page_sidebars_empty' );
                            var _markup = _j[ 'markup' ];
                            _list.innerHTML = _markup;
                            if ( _markup.replace( /\s/gi, '' ) ) {
                                _list.removeAttribute( 'hidden' );
                                _emp.setAttribute( 'hidden', 'hidden' );
                            } else {
                                _list.setAttribute( 'hidden', 'hidden' );
                                _emp.removeAttribute( 'hidden' );
                            }
                        }
                        _list.classList.remove( '-retrieving-data' )
                    } );
                } );
            }
        }
        _list.addEventListener( 'click', function ( _ev ) {
            var _list = _ev.target.closest( '.unlimited_page_sidebars_list' );
            var _id = _ev.target.closest( 'li' ).dataset[ 'sidebarid' ];
            if ( _ev.target.matches( 'span' ) ) {
                if ( confirm( unlimited_page_sidebars.confirm_removal ) ) {
                    _list.say_loading();
                    var _data = new URLSearchParams();
                    _data.append( 'action', 'custom_sidebar_remove' );
                    _data.append( 'id', _id );
                    fetch( ajaxurl, {
                        method: 'post',
                        body: _data
                    } ).then( function ( _r ) {
                        _r.json().then( function ( _j ) {
                            _list.retrieve_list();
                            if ( !_j[ 'id' ] ) alert( _j[ 'message' ] );
                        } );
                    } );
                }
            } else if ( _ev.target.matches( 'a' ) ) {
                var _old_name = _ev.target.innerText;
                var _new_name = prompt( unlimited_page_sidebars.ask_name, _old_name );
                if ( _new_name && ( _new_name != _old_name ) ) {
                    _list.say_loading();
                    var _data = new URLSearchParams();
                    _data.append( 'action', 'custom_sidebar_rename' );
                    _data.append( 'id', _id );
                    _data.append( 'name', _new_name );
                    fetch( ajaxurl, {
                        method: 'post',
                        body: _data
                    } ).then( function ( _r ) {
                        _r.json().then( function ( _j ) {
                            _list.retrieve_list();
                            if ( !_j[ 'id' ] ) alert( _j[ 'message' ] );
                        } );
                    } );
                }
            }
        }, false );
    }
    var _btn = document.querySelector( '.unlimited_page_sidebars_add' );
    _btn.related_list = _list;
    _btn.addEventListener( 'click', function ( _ev ) {
        var _btn = _ev.currentTarget;
        if ( !_btn.classList.contains( 'disabled' ) ) {
            _btn.classList.add( 'disabled' );
            var _name = prompt( unlimited_page_sidebars.ask_name );
            if ( _name ) {
                _btn.related_list.say_loading();
                var _data = new URLSearchParams();
                _data.append( 'action', 'custom_sidebar_add' );
                _data.append( 'name', _name );
                fetch( ajaxurl, {
                    method: 'post',
                    body: _data
                } ).then( function ( _r ) {
                    _r.json().then( function ( _j ) {
                        _btn.related_list.retrieve_list();
                        if ( !_j[ 'id' ] ) alert( _j[ 'message' ] );
                        _btn.classList.remove( 'disabled' );
                    } );
                } );
            } else {
                _btn.classList.remove( 'disabled' )
            }
        }
    } );
} );