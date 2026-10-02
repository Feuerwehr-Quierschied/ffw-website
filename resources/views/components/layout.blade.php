<!doctype html>
<html lang="en" class="dark">
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title ?? 'Freiwillige Feuerwehr Quierschied' }}</title>
</head>
<body class="flex  min-h-screen flex-col">
<!-- Include this script tag or install `@tailwindplus/elements` via npm: -->
<!-- <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script> -->
<nav class="bg-gray-200 text-gray-600 dark:bg-gray-900 dark:text-white  py-4">
    <div class="flex justify-between">
        <button command="--toggle" commandfor="mobile-hamburger-menu" class="md:hidden mr-2">
            Menü
        </button>
        <x-logo class=""/>
        <ul class="hidden md:flex gap-2">
            <li class=""><a href="/">Start</a></li>
            <li><a href="/fahrzeuge">Fahrzeuge</a></li>
            <li><a href="/kontakt">Kontakt</a></li>
        </ul>
    </div>

    <el-disclosure id="mobile-hamburger-menu" hidden class="block md:hidden flex flex-col mt-4 items-start  gap-1 mr-2">
        <ul class="text-gray-300 hover:text-white ">
            <li class="dark:hover:text-red-400"><a href="/">Start</a></li>
            <li class="dark:hover:text-red-400"><a href="/fahrzeuge">Fahrzeuge</a></li>
            <li class="dark:hover:text-red-400"><a href="/kontakt">Kontakt</a></li>
        </ul>
    </el-disclosure>


</nav>

<main class="grow bg-white text-gray-900 dark:bg-gray-950 dark:text-white">{{ $slot }}</main>
<footer class="flex flex-col justify-between">
    <div class="flex flex-row gap-6">
        <ul>
            <li><a>Feuerwehr Quierschied</a></li>
            <li><a>Text</a></li>
        </ul>
        <ul>
            <li><a href="#">Kontakt</a></li>
            <li><a>Schumannstr. 6 <br/> 66287 Quierschied</a></li>
            <li><a href="mailto:feuer@quierschied.de">feuer@quierschied.de</a></li>
        </ul>
        <div>
            Im Notfall: <br/>
            112 <br/>
            <a href="#"><img class="h-6" src="images/facebook-svgrepo-com.svg"></a>

        </div>
    </div>
    <div>
        <a>Feuerwehr Quierschied</a>
    </div>
</footer>



</body>
</html>
