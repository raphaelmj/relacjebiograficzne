var app = angular.module('app',['ngSanitize', 'ngRoute', 'duScroll', 'ngAnimate','perfect_scrollbar','ngCookies'], function($interpolateProvider) {
    $interpolateProvider.startSymbol('[[');
    $interpolateProvider.endSymbol(']]');
});




app.factory('AppService', function($location) {
    return {
        url : $location.protocol()+'://'+$location.host()
    };
});



app.directive('loadingData', function() {
    return {
        templateUrl: 'templates/overload.html'
    };
});





app.filter('checkfalse', function() { return function(obj) {

    if (!(obj instanceof Object)) return false;

    var bool = true;

    Object.keys(obj).forEach(function(key) {

        if(!obj[key]){
            bool = false;
        }

    });

    return bool;
}});


app.filter('capitalize', function() {
    return function(input) {
        return (!!input) ? input.charAt(0).toUpperCase() + input.substr(1).toLowerCase() : '';
    }
});


app.controller('GlobalController',['$scope', '$http', '$log', '$q', '$location','AppService', '$window', '$filter', '$timeout','$rootScope','$cookies', function($scope, $http, $log, $q, $location,AppService, $window, $filter, $timeout,$rootScope,$cookies) {

    $scope.initWcag = function(){

        var body = angular.element(document.getElementById('body'));


        if($cookies.get('wcagstyle')){

            var wcag = JSON.parse($cookies.get('wcagstyle'));

            if(wcag.contrast){
                body.addClass('contrast-plus');
            }else{
                body.removeClass('contrast-plus');
            }

            if(wcag.font){
                body.addClass('font-plus');
            }else{
                body.removeClass('font-plus');
            }
        }

    }

    $timeout(function(){

        angular.element(document.getElementById('body')).removeClass('alphaHide');
        angular.element(document.getElementById('body')).addClass('alphaShow');

    },500);

    $scope.changeWcagStyle = function(type){

        console.log($cookies.get('wcagstyle'));
        if( $cookies.get('wcagstyle') ){
            var wcag = $cookies.get('wcagstyle');
            wcag = JSON.parse(wcag);
        }else{
            var wcag = {
                contrast:false,
                font:false
            }
        }

        var body = angular.element(document.getElementById('body'));

        switch (type){

            case 'cplus':

                body.addClass('contrast-plus');
                wcag.contrast = true;
                $cookies.put('wcagstyle', JSON.stringify(wcag));

                break;

            case 'cminus':

                body.removeClass('contrast-plus');
                wcag.contrast = false;
                $cookies.put('wcagstyle', JSON.stringify(wcag));

                break;

            case 'fplus':

                body.addClass('font-plus');
                wcag.font = true;
                $cookies.put('wcagstyle', JSON.stringify(wcag));

                break;

            case 'fminus':

                body.removeClass('font-plus');
                wcag.font = false;
                $cookies.put('wcagstyle', JSON.stringify(wcag));

                break;

        }

    }

    $http.get(AppService.url+'/get/ajax/auth')
        .then(

            function successCallback(response) {

                //$log.info(response);
                $rootScope.auth = response.data.boolean;

            },
            function errorCallback(response) {

            }
        );



}]);



app.controller('ThreadsController',['$scope', '$http', '$log', '$q', '$location','AppService', '$window', '$filter', '$timeout', '$interval', '$document','$rootScope', function($scope, $http, $log, $q, $location,AppService, $window, $filter, $timeout, $interval, $document,$rootScope) {



    $scope.initData = function(){

        $scope.threads = [];

        $scope.getAllThreads();

    }

    $scope.getAllThreads = function(){

        $http.get(AppService.url+'/get/all/threads')
            .then(
                function successCallback(response){
                    console.log(response.data);
                    $scope.threads = response.data;
                },
                function errorCallback(reason){

                }
            )

    }



}]);