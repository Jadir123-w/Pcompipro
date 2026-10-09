<div class="dropdown cart-dropdown" style="margin-left: 9px;">

    @guest('ecommerce')
        <a class="header-contact mr-0 login-link" href="#" style="text-decoration: none;">
            <img src="{{ asset('images/circle-user.svg') }}" alt="User" style="width: 18px; height: 18px;">
            <strong class="ml-2" style="font-size: 15px; color: #fff;">Log In</strong>
        </a>
    @elseauth('ecommerce')
        <a href="#" class="dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            data-display="static">
            <i class="icon-user fa-2x text-white"></i>
        </a>
        <div class="dropdown-menu">
            <div class="dropdownmenu-wrapper dropdown-ecommerce">
                <div class="dropdown-cart-total mb-0 d-flex flex-column justify-content-start align-items-start pb-3">
                    <span style="font-weight: bold;">{{ Auth::guard('ecommerce')->user()->name }}</span>
                    <span class="text-muted" style="line-height: .6; font-size: 12px;">{{ Auth::guard('ecommerce')->user()->email }} </span>
                </div>
                
                <div class="dropdown-divider"></div>
                
                @unless(request()->is('*/pedidos') || request()->is('*/pedidos/*') || request()->is('pedidos') || request()->is('pedidos/*') || request()->routeIs('tenant_orders') || request()->routeIs('tenant.restaurant.menu'))
                    <a href="{{ route("tenant_document_list") }}" class="dropdown-cart-total dropdown-options span-start d-flex align-items-center text-left text-decoration-none text-dark" >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M19 12v7a1.78 1.78 0 0 1 -3.1 1.4a1.65 1.65 0 0 0 -2.6 0a1.65 1.65 0 0 1 -2.6 0a1.65 1.65 0 0 0 -2.6 0a1.78 1.78 0 0 1 -3.1 -1.4v-14a2 2 0 0 1 2 -2h7l5 5v4.25"></path></svg>
                        <span class="span-start ml-2" style="text-transform: capitalize;">Mis comprobantes</span>
                    </a>
                    
                    <a href="{{ route("tenant_order_list") }}" class="dropdown-cart-total dropdown-options span-start d-flex align-items-center text-left text-decoration-none text-dark" >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path><path d="M17 17h-11v-14h-2"></path><path d="M6 5l14 1l-1 7h-13"></path></svg>
                        <span class="span-start ml-2" style="text-transform: capitalize;">Mis pedidos</span>
                    </a>
                    
                    <div class="dropdown-divider"></div>
                @endunless

                <a href="#" role="menuitem" class="dropdown-cart-total dropdown-options span-start d-flex align-items-center text-left mb-0 text-decoration-none text-dark" data-toggle="tooltip" data-placement="bottom"
                    onclick="event.preventDefault(); console.log('click logout'); document.getElementById('logout-form-header').submit();">
                    <svg  xmlns="http://www.w3.org/2000/svg" width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-logout"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
                    <span class="ml-2">Cerrar sesión</span>
                </a>                

            </div>            
        </div>
        <form id="logout-form-header" action="{{ route('tenant_ecommerce_logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    @endauth

</div>
