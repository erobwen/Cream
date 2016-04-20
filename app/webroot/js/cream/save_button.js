angular.module('pilotApp')

    .directive('saveButton', function() {
        return {
            restrict: 'E',
            scope: {
                'saver': '='
            },
            link: function(scope, element, attrs) {
                scope.init(scope.saver);
            },
            templateUrl: '/js/cream/save_button.html',
            controller: 'SaveButtonController',
        };
    })

    .controller('SaveButtonController', [
        '$scope', function($scope) {
            $scope.controller = 'SaveButtonController';

            addApplicationControllerFunctions($scope);

            $scope.init = function(saver) {
                if ( typeof(saver) !== 'undefined' && saver != null ) {
                    // alert("here");
                    $scope.saver = saver;
                    $scope.saver.postSaveAngularScope = $scope;
                }
            };
        }
    ]);

