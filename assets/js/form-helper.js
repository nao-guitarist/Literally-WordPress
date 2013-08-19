jQuery(document).ready(function($){
	// Check if it is sandbox
	var sandbox = window.location.href.match(/sandbox=true/);
	// Kill every action 
	if(sandbox){
		$('form').submit(function(e){
			e.preventDefault();
		});
		$('#lwp-invoice a').click(function(e){
			e.preventDefault();
		});
	}
	// Prepend user to double submit
	$('form').submit(function(e){
		$(this).find('input[type=submit]').val(LWP.labelProcessing).attr('disabled', true).addClass('disabled');
	});
	// Highlight on change
	$('#lwp-payment-cart select').change(function(){
		$('#lwp-payment-cart .recalculate').effect('highlight', 1000);
	});
	// Force recalculate
	$('#lwp-payment-method-form input[type=submit]').click(function(e){
		flg = false;
		$(this).parents('form').find('input[name^=quantity]').each(function(index,elt){
			var qty = parseInt($(elt).val());
			var qtyInput = $('#lwp-payment-cart select[name="' + $(elt).attr('name') + '"]');
			if(qtyInput.length > 0 && parseInt(qtyInput.val(), 10) !== qty){
				flg = true;
				alert(LWP.labelRecalculating);
				$('html,body').animate({ scrollTop: 0 }, 'fast');
				$('#lwp-payment-cart .recalculate').effect('highlight', 1000);
				return false;
			}
		});
		if(flg){
			e.preventDefault();
		}
	});
	// Radio button helper
	$('#lwp-payment-method-form tr').click(function(e){
		var input = $(this).find('input[type=radio]');
		if(!input.attr('disabled')){
			input.attr('checked', true);
		}
	});
	// Auto Redirect
	if($("#lwp-auto-redirect").length){
		var href = $("#lwp-auto-redirect").attr('href'),
			left = parseInt($('#lwp-redirect-indicator').text(), 10),
			timer = setInterval(function(){
				left--;
				$("#lwp-redirect-indicator").text(left);
				if(left === 0){
					clearInterval(timer);
					if(!sandbox){
						window.location.href = href;
					}
				}
			}, 1000);
	}
	// Chocom Helper
	$('#chocom-redirector').click(function(e){
		e.preventDefault();
		if(!$(this).hasClass('disabled')){
			var savedLabel = $(this).text();
			$(this).addClass('disabled').text(LWP.labelProcessing);
			$('#chocom-transaction').ajaxSubmit({
				dataType: 'json',
				beforeSubmit: function(){
					$('p.message span').css('display', 'none');
					$('p.message span:eq(1)').css('display', 'inline');
					$('p.message').effect('highlight');
					return true;
				},
				success: function(response){
					$('p.message span').css('display', 'none');
					if(response.success){
						$('p.message span:eq(2)').css('display', 'inline');
						var form = $(response.message);
						$('p.message').effect('highlight')
								.removeClass('info').addClass('success')
								.after(form);
						form.submit();
					}else{
						$('p.message span:eq(3)').text(response.message).css('display', 'inline');
						$('p.message').effect('highlight').
								removeClass('info').addClass('error');
					}
					$('#chocom-redirector').removeClass('disabled').text(savedLabel);
				},
				error: function(){
					$('#chocom-redirector').removeClass('disabled').text(savedLabel);
					alert(LWP.onError);
				}
			});
		}
	});
	//Bulk Mail
	$('#lwp-contact-participants-form').submit(function(e){
		e.preventDefault();
		if(confirm(LWP.labelConfirm)){
			//Validate
			var flg = true;
			var msg = [];
			if(!($(this).find('select[name=from]').val().match(/^(admin|you|author)$/))){
				flg = false;
				msg.push(LWP.labelInvalidFrom);
			}
			if(!($(this).find('input[name=subject]').val().length > 0)){
				flg = false;
				msg.push(LWP.labelInvalidSubject);
			}
			if(!($(this).find('textarea[name=body]').val().length > 0)){
				flg = false;
				msg.push(LWP.labelInvalidBody);
			}
			if(!($(this).find('select[name=to]').val().length > 0)){
				flg = false;
				msg.push(LWP.labelInvalidTo);
			}
			if(flg){
				$(this).find('#lwp-contact-indicator').effect('highlight');
				$(this).find('.indicator').fadeIn();
				$(this).find('#lwp-contact-indicator').addClass('processing');
				$(this).find('input[type=submit]').val(LWP.labelSending).attr('disabled', true);
				var submitData = {
					current: 0,
					total: 0,
					_wpnonce: $(this).find('input[name=_wpnonce]').val(),
					action: $(this).find('input[name=action]').val(),
					event_id: $(this).find('input[name=event_id]').val(),
					from: $(this).find('select[name=from]').val(),
					to: $(this).find('select[name=to]').val(),
					subject: $(this).find('input[name=subject]').val(),
					body: $(this).find('textarea[name=body]').val(),
					endpoint: $(this).attr('action')
				};
				function finishLoading(){
					$('#lwp-contact-indicator').removeClass('processing').find('.indicator').fadeOut();
					$('#lwp-contact-participants-form').find('input[type=submit]').removeClass('disabled').attr('disabled', false).val(LWP.labelSent);
				}
				function setCurrentStatus(current, total){
					$('#lwp-contact-indicator .done').text(current);
					$('#lwp-contact-indicator .total').text(total);
					var division = 0;
					if(total > 0){
						division = Math.min(100, Math.round(current / total * 100));
					}
					$('#lwp-contact-indicator .indicator-bar').css('width', division + '%');
					$('#lwp-contact-indicator').effect('highlight');
				}
				function ajaxResponseHandler(result){
					if(result.success){
						submitData.current = result.current;
						submitData.total = result.total
						setCurrentStatus(result.current, result.total);
						if(result.current >= result.total){
							alert(result.message.join("\n"));
							finishLoading();
						}else{
							$.post(submitData.endpoint, submitData, ajaxResponseHandler);
						}
					}else{
						if(result.message){
							alert(result.message.join("\n"));
						}
						$(this).find('input[type=submit]').removeClass('disabled').attr('disabled', false).val(LWP.labelSent);
					}
				}
				$.post(submitData.endpoint, submitData, ajaxResponseHandler);
			}else{
				alert(msg.join("\n"));
				finishLoading();
			}
		}else{
			finishLoading();
		}
	});
});
