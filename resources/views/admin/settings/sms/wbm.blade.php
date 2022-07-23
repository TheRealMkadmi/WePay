@extends('admin.layouts.master')
@section('title', 'SMS Settings')

@section('head_style')
    <!-- bootstrap-toggle -->
    <link rel="stylesheet" href="{{ asset('public/backend/bootstrap-toggle/css/bootstrap-toggle.min.css') }}">
@endsection

@section('page_content')
    <!-- Main content -->
    <div class="row">
        <div class="col-md-3 settings_bar_gap">
            @include('admin.common.settings_bar')
        </div>

        <div class="col-md-9">
            <div class="box box-info">
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs" id="tabs">
                        <li><a href="{{ url(\Config::get('adminPrefix').'/settings/sms/twilio') }}">Twilio</a></li>
                        <li><a href="{{ url(\Config::get('adminPrefix').'/settings/sms/nexmo')}}">Nexmo</a></li>
                        <li class="active"><a href="{{ url(\Config::get('adminPrefix').'/settings/sms/wbm')}}">Wbm</a></li>
                    </ul>
                   
                    <div class="tab-content">
                        <div class="tab-pane fade in active" id="tab_1">
                            <div class="card">
                                <div class="card-header">
                                    <h4></h4>
                                </div>
                                <div class="container-fluid">
                                    <div class="tab-pane" id="tab_2">

                                        <form action="{{ url(\Config::get('adminPrefix').'/settings/sms/wbm') }}" method="POST" class="form-horizontal" id="wbm_sms_setting_form">
                                            {!! csrf_field() !!}


                                            <input type="hidden" name="type" value="{{ base64_encode($wbm->type) }}">


                                            <div class="box-body">


                                                {{-- Name --}}
                                                <div class="form-group" style="display: none;">
                                                    <div class="col-md-10 col-md-offset-1">
                                                        <label class="col-md-3 control-label">Name</label>
                                                        <div class="col-md-8">
                                                            <input type="text" name="name" class="form-control" 
                                                             placeholder="Enter Wbm Sms Gateway Name" id=""
                                                             value="{{ $wbm->type == 'wbm' ? 'Wbm' : '' }}"
                                                             readonly>
                                                            @if ($errors->has('name'))
                                                                <span style="color:red;font-weight:bold;">{{ $errors->first('name') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="clearfix"></div>

                                                {{-- Login --}}
                                                <div class="form-group" >
                                                    <div class="col-md-10 col-md-offset-1">
                                                        <label class="col-md-3 control-label">Wbm Login</label>
                                                        <div class="col-md-8">
                                                            <input type="text" name="wbm[login]" class="form-control"
                                                            value="{{ isset($credentials->login) ? $credentials->login : '' }}"
                                                             placeholder="Enter Wbm Login" >
                                                            @if ($errors->has('login'))
                                                                <span style="color:red;font-weight:bold;">{{ $errors->first('login') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="clearfix"></div>

                                                {{-- Password --}}
                                                <div class="form-group" >
                                                    <div class="col-md-10 col-md-offset-1">
                                                        <label class="col-md-3 control-label">Wbm Password</label>
                                                        <div class="col-md-8">
                                                            <input type="password" name="wbm[password]" class="form-control"
                                                            value="{{ isset($credentials->password) ? $credentials->password : '' }}"
                                                             placeholder="Enter Wbm Password" >
                                                            @if ($errors->has('password'))
                                                                <span style="color:red;font-weight:bold;">{{ $errors->first('password') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="clearfix"></div>



                                                {{-- Compte --}}
                                                <div class="form-group" >
                                                    <div class="col-md-10 col-md-offset-1">
                                                        <label class="col-md-3 control-label">Wbm Compte</label>
                                                        <div class="col-md-8">
                                                            <input type="text" name="wbm[compte]" class="form-control"
                                                            value="{{ isset($credentials->compte) ? $credentials->compte : '' }}"
                                                             placeholder="Enter Wbm Compte" >
                                                            @if ($errors->has('compte'))
                                                                <span style="color:red;font-weight:bold;">{{ $errors->first('compte') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="clearfix"></div>


                                                {{-- Auth Token --}}
                                                <div class="form-group" >
                                                    <div class="col-md-10 col-md-offset-1">
                                                        <label class="col-md-3 control-label">Wbm Authentication Token</label>
                                                        <div class="col-md-8">
                                                            <input type="text" name="wbm[auth_token]" class="form-control"
                                                            value="{{ isset($credentials->auth_token) ? $credentials->auth_token : '' }}"
                                                             placeholder="Enter Wbm Authentication Token" >
                                                            @if ($errors->has('auth_token'))
                                                                <span style="color:red;font-weight:bold;">{{ $errors->first('auth_token') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="clearfix"></div>


                                    

                                                {{-- Status --}}
                                                <div class="form-group">
                                                    <div class="col-md-10 col-md-offset-1">
                                                        <label class="col-md-3 control-label">Status</label>
                                                        <div class="col-md-8">
                                                            <select name="status" class="select2 select2-hidden-accessible">
                                                                <option {{ $wbm->status == 'Active' ? 'selected' : '' }} value="Active">Active</option>
                                                                <option {{ $wbm->status == 'Inactive' ? 'selected' : '' }} value="Inactive">Inactive</option>


                                                            </select>
                                                            @if ($errors->has('status'))
                                                                <span style="color:red;font-weight:bold;">{{ $errors->first('status') }}</span>
                                                            @endif
                                                            <div class="clearfix"></div>
                                                            <h6 class="form-text text-muted"><strong>*Incoming SMS messages might be delayed by {{ ucfirst($wbm->type) }}.</strong></h6>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="clearfix"></div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div style="margin-top:10px">
                                                        <a id="cancel_anchor" href="{{ url(\Config::get('adminPrefix').'/settings/sms/wbm') }}" class="btn btn-theme-danger">Cancel</a>
                                                        <button type="submit" class="btn btn-theme pull-right" id="sms-settings-wbm-submit-btn">
                                                            <i class="fa fa-spinner fa-spin" style="display: none;"></i> <span id="sms-settings-wbm-submit-btn-text">Update</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('extra_body_scripts')

    <!-- jquery.validate -->
    <script src="{{ asset('public/dist/js/jquery.validate.min.js') }}" type="text/javascript"></script>

    <script type="text/javascript">

        $(function () {
            $(".select2").select2({
            });
        });

        $.validator.setDefaults({
            highlight: function(element) {
                $(element).parent('div').addClass('has-error');
            },
            unhighlight: function(element) {
                $(element).parent('div').removeClass('has-error');
            },
            errorPlacement: function (error, element) {
                error.insertAfter(element);
            }
        });


        $('#wbm_sms_setting_form').validate({
            rules: {
                "wbm[login]": {
                    required: true,
                },
                "wbm[password]": {
                    required: true,
                },   
                "wbm[compte]": {
                    required: true,
                },
                "wbm[auth_token]": {
                    required: true,
                },
            },
            messages: {
                "wbm[login]": {
                    required: "Wbm login is required!",
                },
                "wbm[password]": {
                    required: "Wbm password is required!",
                },
                "wbm[compte]": {
                    required: "Wbm compte is required!",
                },
                "wbm[auth_token]": {
                    required: "Wbm Authentication Token is required!",
                },
               
            },
            submitHandler: function(form)
            {
                $("#sms-settings-wbm-submit-btn").attr("disabled", true);
                $(".fa-spin").show();
                $("#sms-settings-wbm-submit-btn-text").text('Updating...');
                $('#cancel_anchor').attr("disabled",true);
                $('#sms-settings-wbm-submit-btn').click(false);
                form.submit();
            }
        });

    </script>
@endpush
