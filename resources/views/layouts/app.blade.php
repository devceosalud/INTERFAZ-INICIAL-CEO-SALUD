<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Title -->
    <title>CEOSALUD</title>

    {{-- CON ESTE COMANDO SE ARREGLO ERROR: 419 --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="DexignZone">
    <meta name="robots" content="">

    <meta name="keywords"
        content="	admin dashboard, admin template, administration, analytics, bootstrap, disease, doctor, elegant, health, hospital admin, medical dashboard, modern, responsive admin dashboard">
    <meta name="description"
        content="Our HTML Admin Dashboard is built with a responsive design, ensuring seamless compatibility across different devices and screen sizes. The user-friendly interface makes navigation intuitive and straightforward for administrators.">

    <meta property="og:title" content="ERES - Hospital Admin Dashboard Bootstrap HTML Template">
    <meta property="og:description"
        content="Our HTML Admin Dashboard is built with a responsive design, ensuring seamless compatibility across different devices and screen sizes. The user-friendly interface makes navigation intuitive and straightforward for administrators.">
    <meta property="og:image" content="https://eres.dexignzone.com/xhtml/social-image.png">
    <meta name="format-detection" content="telephone=no">

    <!-- Mobile Specific -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon icon -->
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png">
    <link href="{{ asset('assets/vendor/owl-carousel/owl.carousel.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
    <link href="https://cdn.lineicons.com/2.0/LineIcons.css" rel="stylesheet">

    <!-- Style Css -->

    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">

</head>

<body>

    @yield('body')


    <!--**********************************
                                    Scripts
                                ***********************************-->
    <!-- Required vendors -->
    <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/chart.js/Chart.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/owl-carousel/owl.carousel.js') }}"></script>

    <!-- Apex Chart -->
    <script src="{{ asset('assets/vendor/apexchart/apexchart.js') }}"></script>

    <!-- Dashboard 1 -->
    <script src="{{ asset('assets/js/dashboard/dashboard-1.js') }}"></script>
    <script src="{{ asset('assets/js/custom.min.js') }}"></script>
    <script src="{{ asset('assets/js/deznav-init.js') }}"></script>



    <script>
        function assignedDoctor() {

            /*  testimonial one function by = owl.carousel.js */
            jQuery('.assigned-doctor').owlCarousel({
                loop: false,
                margin: 30,
                nav: true,
                autoplaySpeed: 3000,
                navSpeed: 3000,
                paginationSpeed: 3000,
                slideSpeed: 3000,
                smartSpeed: 3000,
                autoplay: false,
                dots: false,
                navText: ['<i class="fa fa-caret-left"></i>', '<i class="fa fa-caret-right"></i>'],
                responsive: {
                    0: {
                        items: 1
                    },
                    576: {
                        items: 2
                    },
                    767: {
                        items: 3
                    },
                    991: {
                        items: 2
                    },
                    1200: {
                        items: 3
                    },
                    1600: {
                        items: 4
                    },
                    1920: {
                        items: 5
                    }
                }
            })
        }

        jQuery(window).on('load', function() {
            setTimeout(function() {
                assignedDoctor();
                console.log('TEST');
            }, 1000);
        });
    </script>

</body>

</html>