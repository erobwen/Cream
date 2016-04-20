(function() {
    'use strict';

    if ( !Array.prototype.last ) {
        Array.prototype.last = function() {
            return this[this.length - 1];
        };
    }

}());

// This is similar to a merge structure, but not quite, as fields not present in the source will be removed from the destination. 
function deepCopyIntoRecursive(source, destination) {
    if (Array.isArray(source)) {
        if (Array.isArray(destination)) {
            destination.length = 0;
            source.forEach(function (item) {
                destination.push(angular.copy(item));
            });
        } else {
        }
    } else if (typeof(source) === 'object' && typeof(destination) === 'object') {
        for (var property in source) {
            if (typeof(destination[property]) !== 'undefined') {
                deepCopyIntoRecursive(source[property], destination[property]);
            } else {
                destination[property] = angular.copy(source[property]);
            }
        }
    }
}

function capitalize(s) {
    return s[0].toUpperCase() + s.slice(1);
}

function clearArray(arr) {
	// arr.length = 0;
	arr.splice(0, arr.length);
}

function isArray(entity) {
	return Array.isArray(entity);
}

function lastOfArray(array){
	return array[array.length - 1];
}

function pushArray(destinationArray, pushedArray) {
	pushedArray.forEach(function(element) {
		destinationArray.push(element);
	});
}

// This is different in functionality from jQuery.inArray that returns an integer that has to be further checked.
function inArray(item, array) {
	var result;
	array.forEach(function(arrayItem) {
		if (item === arrayItem) {
			result = true;
		}
	});
	return result;
}

// Convenience function
function emptyAsNull(string) {
	if (string == "") {
		return null;
	} else {
		return string;
	}
}

function removeFromArray(object, array) {
	var limit = array.length;
	// console.log(limit);
	for (var i=0; i < limit; i++) {
		// console.log("Comparing");
		// console.log(array[i]);
		// console.log(object);
		if (array[i] == object) {
			array.splice(i, 1);
			return true;
		}
	}
	// console.log("not found");
	return false;
}

function startsWithCapital(string) {
	return string[0] === string[0].toUpperCase();
} 