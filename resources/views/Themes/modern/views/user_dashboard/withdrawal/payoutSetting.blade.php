@extends('user_dashboard.layouts.app')

@section('content')
<section class="min-vh-100">
    <div class="mt-30">
        <div class="container-fluid">
            <!-- Page title start -->
            <div class="d-flex justify-content-between">
                <div>
                    <h3 class="page-title">{{ __('Withdrawals') }}</h3>
                </div>

                <div>
                    <a href="{{ url('/payout') }}">
                        <button class="btn btn-primary px-4 py-2" data-toggle="modal" data-target="#addModal" id="addBtn">
                            <i class="fa fa-plus"></i> @lang('message.dashboard.payout.payout-setting.add-setting')
                        </button>
                    </a>
                </div>
            </div>

            <!-- Page title end-->
            <div class="mt-4 border-bottom">
                <div class="d-flex flex-wrap justify-content-between">
                    <div>
                        <div class="d-flex flex-wrap">
                            <a href="{{url('/payouts')}}">
                                <div class="mr-4 pb-3">
                                    <p class="text-16 font-weight-400 text-gray-500">{{ __('Payout list') }}</p>
                                </div>
                            </a>

                            <a href="{{url('/payout/setting')}}">
                                <div class="mr-4 border-bottom-active pb-3">
                                    <p class="text-16 font-weight-600 text-active">{{ __('Payout settings') }}  </p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-12">
                            @include('user_dashboard.layouts.common.alert')
                            <div class="bg-secondary mt-3 shadow">
                                <div class="table-responsive">
                                    @if($payoutSettings->count() > 0)
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th class="pl-5">@lang('message.dashboard.payout.payout-setting.payout-type')</th>
                                                    <th>@lang('message.dashboard.payout.payout-setting.account')</th>
                                                    <th class="pr-5 text-right">@lang('message.dashboard.payout.payout-setting.action')</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach($payoutSettings as $row)
                                                    <tr class="row_id_{{$row->id}}">
                                                        <td class="pl-5">
                                                            <p class="">{{$row->paymentMethod->name}}</p>
                                                        </td>

                                                        <td>
                                                            @if($row->paymentMethod->id == 3)
                                                                {{$row->email }}
                                                            @else
                                                                {{$row->account_name}} (*****{{substr($row->account_number,-4)}}
                                                                )<br/>
                                                                {{$row->bank_name}}
                                                            @endif
                                                        </td>
                                                        <td class="pr-5 text-right">
                                                            <!-- <a data-id="{{$row->id}}" data-type="{{$row->type}}" data-obj="{{json_encode($row->getAttributes())}}" class="btn btn-sm btn-light mr-lg-2 mt-2 edit-setting"><i class="far fa-edit"></i></a> -->
                                                            <button class="btn btn-sm btn-light mr-lg-2 mt-2 edit-setting" data-id="{{$row->id}}" data-toggle="modal" data-target="#editModal" data-type="{{$row->type}}" data-obj="{{json_encode($row->getAttributes())}}" id="editBtn">
                                                                <i class="far fa-edit"></i>
                                                            </button>
                                                            <form action="{{url('payout/setting/delete')}}" method="post" style="display: inline">
                                                                @csrf
                                                                <input type="hidden" name="id" value="{{$row->id}}">
                                                                <a class="btn btn-sm btn-light mt-2 delete-setting" data-toggle="modal" data-target="#delete-warning-modal" data-title="{{__("Delete Data")}}"
                                                                data-message="{{__("Are you sure you want to delete this Data ?")}}" data-row="{{$row->id}}" href=""><i class="fa fa-trash"></i></a>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <div class="p-5 text-center">
                                            <svg width="96" height="96" fill="none" class="mx-auto mb-6 text-gray-900"><path d="M36 28.024A18.05 18.05 0 0025.022 39M59.999 28.024A18.05 18.05 0 0170.975 39" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><ellipse cx="37.5" cy="43.5" rx="4.5" ry="7.5" fill="currentColor"></ellipse><ellipse cx="58.5" cy="43.5" rx="4.5" ry="7.5" fill="currentColor"></ellipse><path d="M24.673 75.42a9.003 9.003 0 008.879 5.563m-8.88-5.562A8.973 8.973 0 0124 72c0-7.97 9-18 9-18s9 10.03 9 18a9 9 0 01-8.448 8.983m-8.88-5.562C16.919 68.817 12 58.983 12 48c0-19.882 16.118-36 36-36s36 16.118 36 36-16.118 36-36 36a35.877 35.877 0 01-14.448-3.017" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M41.997 71.75A14.94 14.94 0 0148 70.5c2.399 0 4.658.56 6.661 1.556a3 3 0 003.999-4.066 12 12 0 00-10.662-6.49 11.955 11.955 0 00-7.974 3.032c1.11 2.37 1.917 4.876 1.972 7.217z" fill="currentColor"></path></svg>
                                            <p>{{ __('Sorry!') }} @lang('message.dashboard.payout.list.not-found')</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4">
                                {{ $payoutSettings->links('vendor.pagination.bootstrap-4') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- addModal Modal-->
<div class="modal fade" id="addModal" role="dialog">
    <div class="modal-dialog modal-lg">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header" style="display: block;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h3 class="modal-title text-18 font-weight-600">@lang('message.dashboard.payout.payout-setting.modal.title')</h3>
            </div>
            <div class="modal-body">
                <form id="payoutSettingForm" method="post">
                    {{csrf_field()}}
                    <div id="settingId"></div>
                    <div class="col-md-12" id="method_list">
                        <div class="form-group">
                            <label>@lang('message.dashboard.payout.payout-setting.payout-type')</label>
                            <select name="type" id="type" class="form-control">
                                @foreach($paymentMethods as $method)
                                    @if($method->name == "CompteStb")
                                        <option value="{{$method->name}}">Compte STB</option>
                                    @elseif ($method->name == "CarteStb")
                                        <option value="{{$method->name}}">Carte STB</option>
                                    @elseif ($method->name == "Clictopay")

                                    @else
                                        <option value="{{$method->id}}">{{$method->name}}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div id="bankForm">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.bank-account-holder-name')</label>
                                <input name="account_name" class="form-control">

                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.account-number')</label>
                                <input name="account_number" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">

                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.swift-code')</label>
                                <input name="swift_code" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.bank-name')</label>
                                <input name="bank_name" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-name')</label>
                                <input name="branch_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-city')</label>
                                <input name="branch_city" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-address')</label>
                                <input name="branch_address" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.country')</label>
                                <select name="country" class="form-control">
                                    @foreach($countries as $country)
                                        <option value="{{$country->id}}">{{$country->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>
                    <div id="paypalForm" style="margin:0 auto;display: none">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.email')</label>
                                <input name="email" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">
                            </div>
                        </div>
                    </div>
                    <div id="compteStbForm">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nom du titulaire du compte *</label>
                                <input name="account_name1" class="form-control">

                            </div>
                            <div class="form-group">
                                <label>RIB  *</label>
                                <input name="account_number1" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">

                            </div>
                            <div class="form-group">
                                <label>CIN  *</label>
                                <input name="swift_code1" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">
                            </div>
                                
                        </div>

                        <div class="col-md-6">                   
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-city')</label>
                                <input name="branch_city1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-address')</label>
                                <input name="branch_address1" class="form-control">
                            </div>                                
                        </div>
                    </div>


                    <div id="cartStbForm">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nom du titulaire de la carte *</label>
                                <input name="account_name2" class="form-control">

                            </div>
                            <div class="form-group">
                                <label>Numéro de la carte  *</label>
                                <input name="account_number2" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">

                            </div>
                            <div class="form-group">
                                <label>CIN  *</label>
                                <input name="swift_code2" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">
                            </div>
                                
                        </div>
                        <div class="col-md-6">
                               
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-city')</label>
                                <input name="branch_city2" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-address')</label>
                                <input name="branch_address2" class="form-control">
                            </div>
                                
                        </div>

                    </div>
                    <div class="row m-0">
                        <div class="col-md-12 pb-2">
                            <button type="submit" class="btn btn-primary px-4 py-2" id="submit_btn">
                                <i class="spinner fa fa-spinner fa-spin" style="display: none;"></i> <span id="submit_text">@lang('message.form.submit')</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- editModal Modal-->
<div class="modal fade" id="editModal" role="dialog">
    <div class="modal-dialog modal-lg">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header" style="display: block;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h3 class="modal-title text-18 font-weight-600">@lang('message.dashboard.payout.payout-setting.modal.title')</h3>
            </div>
            <div class="modal-body">
                <form id="payoutSettingForm" method="post">
                    {{csrf_field()}}
                    <div id="settingId"></div>
  
                    <div id="compteStbForm">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nom du titulaire du compte *</label>
                                <input name="account_name1" class="form-control">

                            </div>
                            <div class="form-group">
                                <label>RIB  *</label>
                                <input name="account_number1" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">

                            </div>
                            <div class="form-group">
                                <label>CIN  *</label>
                                <input name="swift_code1" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">
                            </div>
                                
                        </div>

                        <div class="col-md-6">                   
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-city')</label>
                                <input name="branch_city1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-address')</label>
                                <input name="branch_address1" class="form-control">
                            </div>                                
                        </div>
                    </div>


                    <div id="cartStbForm">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nom du titulaire de la carte *</label>
                                <input name="account_name2" class="form-control">

                            </div>
                            <div class="form-group">
                                <label>Numéro de la carte  *</label>
                                <input name="account_number2" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">

                            </div>
                            <div class="form-group">
                                <label>CIN  *</label>
                                <input name="swift_code2" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">
                            </div>
                                
                        </div>
                        <div class="col-md-6">
                               
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-city')</label>
                                <input name="branch_city2" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.modal.branch-address')</label>
                                <input name="branch_address2" class="form-control">
                            </div>
                                
                        </div>

                    </div>
                    <div class="row m-0">
                        <div class="col-md-12 pb-2">
                            <button type="submit" class="btn btn-primary px-4 py-2" id="submit_btn">
                                <i class="spinner fa fa-spinner fa-spin" style="display: none;"></i> <span id="submit_text">@lang('message.form.submit')</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')

    <script src="{{asset('public/user_dashboard/js/jquery.validate.min.js')}}" type="text/javascript"></script>
    <script src="{{asset('public/user_dashboard/js/additional-methods.min.js')}}" type="text/javascript"></script>

    @include('user_dashboard.layouts.common.check-user-status')

    <script>
        //Clear validation errors on modal close - starts
        $(document).ready(function() {
            $('#addModal').on('hidden.bs.modal', function (e) {
                $('#payoutSettingForm').validate().resetForm();
                $('#payoutSettingForm').find('.error').removeClass('error');
            });
        });
        //Clear validation errors on modal close - ends
        $(document).ready(function(){
            $('#bankForm').hide();
            $('#compteStbForm').hide();
            $('#cartStbForm').hide();
            $('#paypalForm').css('display', 'flex');
        });
        $('#type').on('change', function()
        {
            if ($('option:selected', this).text() == 'Paypal')
            {
                $('#bankForm').hide();
                $('#compteStbForm').hide();
                $('#cartStbForm').hide();
                $('#paypalForm').css('display', 'flex');
            }
            else if ($('option:selected', this).text() == 'Bank')
            {
                $('#bankForm').css('display', 'flex');
                $('#compteStbForm').hide();
                $('#cartStbForm').hide();
                $('#paypalForm').hide();
            }
            else if ($('option:selected', this).text() == 'Compte STB')
            {
                $('#compteStbForm').css('display', 'flex');
                $('#bankForm').hide();
                $('#cartStbForm').hide();
                $('#paypalForm').hide();
            }
            else if ($('option:selected', this).text() == 'Carte STB')
            {
                $('#cartStbForm').css('display', 'flex');
                $('#bankForm').hide();
                $('#compteStbForm').hide();
                $('#paypalForm').hide();
            }
        });
        $('#addBtn').on('click', function(e)
        {
            e.preventDefault();
            //if user is suspended
            checkUserSuspended(e);
            //if user is not suspended
            $('#settingId').html('');
            var form = $('#payoutSettingForm');
            form.attr('action', '{{url('payout/setting/store')}}');
            $.each(form[0].elements, function(index, elem)
            {
                if (elem.name != "_token" && elem.name != "setting_id")
                {
                    $(this).val("");
                    if (elem.name == "type")
                    {
                        $(this).val(1).change().removeAttr('disabled');
                    }
                }
            });
        });
        jQuery.extend(jQuery.validator.messages,
        {
            required: "{{__('This field is required.')}}",
        })
        $('#payoutSettingForm').validate(
        {
            rules:
            {
                type:
                {
                    required: true
                },
                account_name:
                {
                    required: true
                },
                account_name1:
                {
                    required: true
                },
                account_name2:
                {
                    required: true
                },
                account_number:
                {
                    required: true
                },
                account_number1:
                {
                    required: true
                },
                account_number2:
                {
                    required: true
                },
                swift_code:
                {
                    required: true
                },
                swift_code1:
                {
                    required: true
                },
                swift_code2:
                {
                    required: true
                },
                bank_name:
                {
                    required: true
                },
                branch_name:
                {
                    required: true
                },
                branch_city:
                {
                    required: false
                },
                branch_city1:
                {
                    required: false
                },
                branch_city2:
                {
                    required: false
                },
                branch_address:
                {
                    required: false,
                },
                branch_address1:
                {
                    required: false,
                },
                branch_address2:
                {
                    required: false,
                },
                email:
                {
                    required: true,
                    email: true
                },
                country:
                {
                    required: true
                },
            },
            submitHandler: function(form)
            {
                $("#submit_btn").attr("disabled", true);
                $(".spinner").show();
                $("#submit_text").text("{{__('Submitting...')}}");
                form.submit();
            }
        });
        $('#editBtn').on('click', function(e)
        {
            e.preventDefault();
            checkUserSuspended(e);
            // return false;
            //if user is not suspended
            var obj = JSON.parse($(this).attr('data-obj'));
            var settingId = $(this).attr('data-id');
            var form = $('#payoutSettingForm');
            form.attr('action', '{{url('payout/setting/update')}}');
            form.attr('method', 'post');
            var html = '<input type="hidden" name="setting_id" value="' + settingId + '">';
            $('#settingId').html(html);
            if (obj.type == 6)
            {
                if(obj.bank_name == "STB"){
                    $.each(form[0].elements, function(index, elem)
                {  
                    switch (elem.name)
                    {
                        case "type":
                            $(this).val("CompteStb").change().attr('disabled', 'true');
                            break;
                        case "account_name1":
                            $(this).val(obj.account_name);
                            break;
                        case "account_number1":
                            $(this).val(obj.account_number);
                            break;
                        case "branch_address1":
                            $(this).val(obj.bank_branch_address);
                            break;
                        case "branch_city1":
                            $(this).val(obj.bank_branch_city);
                            break;
                        case "swift_code1":
                            $(this).val(obj.swift_code);
                            break;
                        default:
                            break;
                    }
                })
                    
                }else if(obj.bank_name == "CartSTB"){
                    $.each(form[0].elements, function(index, elem)
                {  
                    switch (elem.name)
                    {
                        case "type":
                            $(this).val("CarteStb").change().attr('disabled', 'true');
                            break;
                        case "account_name2":
                            $(this).val(obj.account_name);
                            break;
                        case "account_number2":
                            $(this).val(obj.account_number);
                            break;
                        case "branch_address2":
                            $(this).val(obj.bank_branch_address);
                            break;
                        case "branch_city2":
                            $(this).val(obj.bank_branch_city);
                            break;
                        case "swift_code2":
                            $(this).val(obj.swift_code);
                            break;
                        default:
                            break;
                    }
                })
                }else{
                    $.each(form[0].elements, function(index, elem)
                {  
                    
                    switch (elem.name)
                    {
                        case "type":
                            $(this).val(obj.type).change().attr('disabled', 'true');
                            break;
                        case "account_name":
                            $(this).val(obj.account_name);
                            break;
                        case "account_number":
                            $(this).val(obj.account_number);
                            break;
                        case "branch_address":
                            $(this).val(obj.bank_branch_address);
                            break;
                        case "branch_city":
                            $(this).val(obj.bank_branch_city);
                            break;
                        case "branch_name":
                            $(this).val(obj.bank_branch_name);
                            break;
                        case "bank_name":
                            $(this).val(obj.bank_name);
                            break;
                        case "country":
                            $(this).val(obj.country);
                            break;
                        case "swift_code":
                            $(this).val(obj.swift_code);
                            break;
                        default:
                            break;
                    }
                })
                }
                
            }
            else if (obj.type == 3)
            {
                $.each(form[0].elements, function(index, elem)
                {
                    if (elem.name == 'email')
                    {
                        $(this).val(obj.email);
                    }
                    else if (elem.name == 'type')
                    {
                        $(this).val(obj.type).change().attr('disabled', 'true');
                    }
                })
            }
            setTimeout(()=>{
                $('#addModal').modal();
            }, 400)
            // $('#addModal').modal();
        });
        $('.delete-setting').on('click', function(e)
        {
            e.preventDefault();
            checkUserSuspended(e);
        });
    </script>
@endsection
