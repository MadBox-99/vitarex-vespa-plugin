document.addEventListener('DOMContentLoaded', function() {
    var ajaxForms = document.querySelectorAll('.ajax-form');
    for(var ajaxForm of ajaxForms){
        ajaxForm.addEventListener('submit', function(e){
            var formElement = e.target;
            e.preventDefault();

            var submitButton = formElement.querySelector('[type=submit]');
            submitButton.disabled = true;

            var errors = formElement.querySelectorAll('.is-invalid');
            if(errors){
                for(error of errors){
                    error.classList.remove('is-invalid');
                }
            }

            var errormessages = formElement.querySelectorAll('.invalid-feedback');
            if(errormessages){
                for(error of errormessages){
                    error.remove();
                }
            }

            var musthide = formElement.querySelectorAll('.hide-on-ajax');
            if(musthide){
                for(mh of musthide){
                    mh.style.display = 'none';
                }
            }

            // TinyMCE tartalom szinkronizálása a textarea-ba küldés előtt
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }

            var data = new FormData(this);

            var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
            xhr.open('POST', vitarex_vespa_ajaxurl);
            xhr.onreadystatechange = function() {

                submitButton.disabled = false;

                if (xhr.readyState>3 && xhr.status==200) {
                    var response = JSON.parse(xhr.responseText);
                    if(response.success === true){
                        if(response.data.redirect) document.location.href = response.data.redirect;

        
                        if(response.data.modal) {
                            document.body.insertAdjacentHTML('beforeend', response.data.modal );
                            jQuery('#' + response.data.modalId ).modal('show');
                        }

/*                  
                        if(response.data.notification) UIkit.notification(response.data.notification);
*/

                        if(response.data.script){
                            eval(response.data.script);
                        }

                        if(response.data.content){
                            if(response.data.type && response.data.type == 'append'){
                                document.getElementById(response.data.target).insertAdjacentHTML('beforeend', response.data.content);
                            } else if(response.data.type && response.data.type == 'prepend'){
                                document.getElementById(response.data.target).insertAdjacentHTML('afterbegin', response.data.content);
                            } else {
                            	document.getElementById(response.data.target).style.display = 'block';
                                document.getElementById(response.data.target).innerHTML = response.data.content;
                            }
                        }
                    } else {

                        if(response.data.errors){
                            for(var field in response.data.errors){
                                var elems = formElement.querySelectorAll('[name='+field+']');

                                if( elems.length == 0 ){
                                    elems = formElement.querySelectorAll('[name*='+field+']');
                                }

                                for(elem of elems){
                                    elem.classList.add('is-invalid');
                                    if(response.data.errors[field] != null){
                                    	if( elem.type == 'checkbox'){
                                    		document.getElementById( field + "_label").insertAdjacentHTML('afterend', '<div class="invalid-feedback">'+ response.data.errors[field] +'</div>');
                                    	}
                                    	else {
                                        	elem.insertAdjacentHTML('afterend', '<div class="invalid-feedback">'+ response.data.errors[field] +'</div>');
                                    	}

                                    }
                                }
                            }
                        }
                    }
                }
            };
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(data);
        });
    }
});