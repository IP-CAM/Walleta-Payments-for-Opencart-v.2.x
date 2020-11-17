<form id="walleta-payment" class="form-horizontal">
    <div id="walleta-error"></div>

    <div class="form-group required">
        <label class="col-sm-2 control-label" for="input-payer-mobile"><?php echo $entry_mobile;?></label>
        <div class="col-sm-10">
            <input type="text" name="payer_mobile" id="input-payer-mobile" class="form-control" maxlength="11">
        </div>
    </div>

    <div class="form-group required">
        <label class="col-sm-2 control-label" for="input-payer-national-code"><?php echo $entry_national_code;?></label>
        <div class="col-sm-10">
            <input type="text" name="payer_national_code" id="input-payer-national-code" class="form-control" maxlength="10">
        </div>
    </div>
</form>

<div class="buttons">
    <div class="pull-right">
        <button type="button" class="btn btn-primary" id="walleta-confirm">
            <?php echo $button_confirm; ?>
        </button>
    </div>
</div>

<script type="text/javascript">
    var isClicked = false;
    $('#walleta-confirm').on('click', function () {
        if (isClicked === false) {
            isClicked = true;
            $.ajax({
                type: 'POST',
                url: '<?php echo $action; ?>',
                cache: false,
                dataType: 'JSON',
                data: $('#walleta-payment :input'),
                beforeSend: function () {
                    $('#walleta-error').empty();
                    $('#walleta-confirm').css('cursor', 'wait');
                },
                complete: function () {
                    $('#walleta-confirm').css('cursor', 'pointer');
                    isClicked = false;
                },
                success: function (response) {
                    if (response.status === 'success') {
                        isClicked = true;
                        location = response.redirect;
                    } else {
                        $.each(response.errors, function (i, error) {
                            $('#walleta-error').append('<div class="alert alert-danger"><i class="fa fa-info-exclamation"></i>' + error + '</div>');
                        });
                    }
                }
            });
        }
    });
</script>
