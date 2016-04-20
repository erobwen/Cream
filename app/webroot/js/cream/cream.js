var cream = {
    classRegistry: {},
    classRegistryLinked: false,
    idObjectMap: {},
	
    initialize: function() {
        cream.defaultSaver = createSaver('Default Saver');
        cream.defaultSaver.autosave = true;
    },
	
	
	/****************
	*  Server calls 
	*****************/
	
    callOnServer: function(object, method, postCallCallback) { // TODO: an arglist
        var message = {entityId: object.entityId, method: method};

        $.ajax({
            data: message,
            dataType: 'html',
            success: function(text, textStatus) {
                try {
                    changelist = JSON.parse(text);
                    globalAngularScope.$apply(function() {
                        cream.applyServerChangeList(changelist);
                        postCallCallback();
                    });
                }
                catch (error) {
                    if ( typeof(errorCallback) !== 'undefined' ) {
                        errorCallback();
                    }
                }
            },
            fail: function(text, textStatus) {
            },
            type: 'post',
            url: '/cream/call/'
        });
    },

	
	/*********************************
	* repeatOnChange (watch handling) 
	**********************************/
	
	traceRepetition : false,
	repeatersBeeingRefreshed: [],
	onLoadRepeaters:[], // This would also include crashed repeaters. Check it if a repeater is not functioning correctly! 
	dirtyRepeaters:[],
	allRepeaters: [], // Needed for debug only
	repeaterId:0,
	repeatOnChange: function() {
		// Arguments
		var repeaterCallback;
		var description = null;
		if (arguments.length > 1) {
			description = arguments[0];
			repeaterCallback = arguments[1];
		} else {
			repeaterCallback = arguments[0];			
		}
			
		var repeater = {
			id : cream.repeaterId++,
			callback : repeaterCallback,
			sources : [],
			childRepeaters: [],
			dirty : true,
			loadErrorMessage: null,
			description : description !== null ? description : ""
		};

		if (cream.repeatersBeeingRefreshed.length > 0) {
			var parentRepeater = lastOfArray(cream.repeatersBeeingRefreshed);
			parentRepeater.childRepeaters.push(repeater);
		}

		cream.allRepeaters.push(repeater);
		// if (cream.allRepeaters.length == 10) {
			// debugger;
		// }
		// console.log("repeatOnChange activated: " + repeater.id + "." + description);
		cream.refreshRepeater(repeater);
	},
		
	holdingChangePropagation: false,
	holdChangePropagation: function(callback) {
		cream.holdingChangePropagation = true;
		callback();
		cream.holdingChangePropagation = false;
		cream.refreshAllDirtyRepeaters();
	},
		
	repeaterDirty: function(repeater) {
		if (!repeater.dirty) {
			if (cream.traceRepetition) {
				console.log("Repeater dirty: " + repeater.id + "." + repeater.description);
			}
			repeater.dirty = true;
			cream.dirtyRepeaters.push(repeater);
			cream.tryToRefreshAllDirtyRepeaters();
		}
	},
	
	tryToRefreshAllDirtyRepeaters : function() {
		if (!cream.holdingChangePropagation) {
			cream.refreshAllDirtyRepeaters();
		}
	},

	refreshingAllDirtyRepeaters : false,
	refreshAllDirtyRepeaters : function() {
		if (!cream.refreshingAllDirtyRepeaters) {
			if (cream.traceRepetition) {
				console.log("Starting refresh of all dirty repeaters, current count of dirty:" + cream.dirtyRepeaters.length + ". Currently waiting for load:" + cream.onLoadRepeaters.length);
			}
			cream.refreshingAllDirtyRepeaters = true;
			while(cream.dirtyRepeaters.length > 0) {
				var repeater = cream.dirtyRepeaters.shift();
				cream.refreshRepeater(repeater);
			}
			cream.refreshingAllDirtyRepeaters = false;			
			if (cream.traceRepetition) {
				console.log("Finished refresh of all dirty repeaters, current count of dirty:" + cream.dirtyRepeaters.length + ". Currently waiting for load:" + cream.onLoadRepeaters.length);
				// console.log("==============");
				console.log("Still waiting on load:");
				console.log(cream.onLoadRepeaters); // These will also include crashed repeaters.
				console.log("All repeaters:");
				console.log(cream.allRepeaters);
				// console.log("==============");
			}
		}
	},
	
	notifyOnLoadRepeaters : function() {
		if (cream.traceRepetition) {
			console.log("Notify load observers:" + cream.onLoadRepeaters.length);
			console.log(cream.onLoadRepeaters);
			// console.log(cream.dirtyRepeaters);
		}
		// console.log(cream.onLoadRepeaters);
		var stillNeedsToWait = [];
		cream.onLoadRepeaters.forEach(function(observer) {
			// console.log(observer.id + "." + observer.description);
			// console.log(observer.waitingForObjectToLoad.loaded);
			// console.log(observer.waitingForObjectToLoad);
			if (observer.waitingForObjectToLoad.loaded) {
				if (cream.traceRepetition) {
					console.log("Restarting repeater " + observer.id + "." + observer.description + " after load");
				}
				cream.dirtyRepeaters.push(observer);
			} else {
				stillNeedsToWait.push(observer);
			}
		});
		clearArray(cream.onLoadRepeaters);
		stillNeedsToWait.forEach(function(item) {
			cream.onLoadRepeaters.push(item);
		});
		if (cream.traceRepetition) {
			console.log("Still needs to wait:");
			console.log(cream.onLoadRepeaters); // These will also include crashed repeaters.
		}
		cream.refreshAllDirtyRepeaters();
	},
	
	removeObservation : function(repeater) {
		// Clear out previous observations
		repeater.sources.forEach(function(source) { // From observed object
			delete source.object.observers[source.propertyOrRelation][repeater.id];
		});
		clearArray(repeater.sources);  // From repeater itself.
	},	
	
	
	removeSubRepeaters : function(repeater) {
		if (repeater.childRepeaters.length > 0) {
			// console.log("Removing sub repeaters");
			// console.log(cream.allRepeaters);
			repeater.childRepeaters.forEach(function(repeater) {
				cream.removeRepeater(repeater);
			});
			repeater.childRepeaters.length = 0;
			// console.log(cream.allRepeaters);
		}
	},
	
	removeRepeater : function(repeater) {
		// console.log("Remove repeater");
		// console.log(repeater);
		if (repeater.childRepeaters.length > 0) {
			repeater.childRepeaters.forEach(function(repeater) {
				cream.removeRepeater(repeater);
			});
			repeater.childRepeaters.length = 0;
		}
		
		cream.removeObservation(repeater);
		//removeFromArray(repeater, cream.repeatersBeeingRefreshed);
		removeFromArray(repeater, cream.onLoadRepeaters);
		removeFromArray(repeater, cream.dirtyRepeaters);
		removeFromArray(repeater, cream.allRepeaters);		
	},
	
	refreshAccumulatedTime : 0,
	measuringTime : false,
	refreshRepeater : function(repeater) {
		var start = new Date().getTime();
		var startedToMeasureTime = !cream.measuringTime;
		cream.measuringTime = true;
		// var trace = repeater.childRepeaters.length > 0;
		if (cream.traceRepetition) {
			console.log("Refreshing repeater " + repeater.id + "." + repeater.description);
		}
		cream.removeObservation(repeater);
		cream.removeSubRepeaters(repeater);
		// if (trace) { 
			// console.log(repeater); 
			// console.log(cream.allRepeaters); 
			// debugger; }
		cream.repeatersBeeingRefreshed.push(repeater);
		try {
			repeater.callback();
			repeater.dirty = false;
			repeater.loadErrorMessage = null;
		} catch (error) {
			if (cream.waitingForIsLoadedOrException ) { //error.message == "Error: Accessing unloaded data"         error.message.indexOf("get") !== -1 || error.message.indexOf("set") !== -1) {
				cream.waitingForIsLoadedOrException = false;
				if (cream.traceRepetition) {
					console.log("Could not refresh repeater " + repeater.id + "." + repeater.description + " because of unloaded data. Try again on each dataload.");
				}
				// console.log(error.message);
				repeater.dirty = true;
				repeater.loadErrorMessage = error.message;
				cream.removeObservation(repeater);
				cream.onLoadRepeaters.push(repeater);				
			} else {
				throw(error);
			}
		}
		cream.repeatersBeeingRefreshed.pop();

		if (startedToMeasureTime) {
			var end = new Date().getTime();
			var time = (end - start);
			cream.refreshAccumulatedTime += time;

			if (cream.traceRepetition) {
				console.log("Finished refresing clocked repeater. Total time spent on refresh so far:" + cream.refreshAccumulatedTime);
			}
			// console.log(cream.allRepeaters);
			// debugger;
			cream.measuringTime = false;
		}
	},
	
	readingPropertyOrRelation : function(object, propertyOrRelation) {
		if (cream.repeatersBeeingRefreshed.length > 0) {
			var repeaterBeeingRefreshed = cream.repeatersBeeingRefreshed[cream.repeatersBeeingRefreshed.length - 1];
			// console.log("Reading property " + object.entityId + "." + propertyOrRelation + " with repeater " + repeaterBeeingRefreshed.id);

			// Ensure observer structure in place
			if (typeof(object.observers) == 'undefined') {
				object.observers = {};
			}
			if(typeof(object.observers[propertyOrRelation]) === 'undefined') {
				object.observers[propertyOrRelation] = {};
			}
			
			// Add repeater on object beeing observed, if not already added before
			if (typeof(object.observers[propertyOrRelation][repeaterBeeingRefreshed.id]) === 'undefined') {
				object.observers[propertyOrRelation][repeaterBeeingRefreshed.id] = repeaterBeeingRefreshed;
				
				// Note dependency in repeater itself (for cleaning up)
				repeaterBeeingRefreshed.sources.push({object : object, propertyOrRelation: propertyOrRelation});				
			}
		}
	},
	
	
	/******************
	*  Schema handling
	*******************/
	
    linkCreamClasses: function() {
        for (var creamClassName in cream.classRegistry) {
            var creamClass = cream.classRegistry[creamClassName];
            if ( angular.isDefined(creamClass._extends) ) {
                creamClass._extends = cream.classRegistry[creamClass._extends];
            }
        }
    },

    ensureClassRegistryLinked: function() {
        if ( !cream.classRegistryLinked ) {
            cream.linkCreamClasses();
            cream.classRegistryLinked = true;
        }
    },

    addMethodsRecursivley: function(creamClass, object) {
        if ( typeof(creamClass._extends) !== 'undefined' ) {
            cream.addMethodsRecursivley(creamClass._extends, object);
            creamClass.addMethods(object);
        }
        else {
            creamClass.addMethods(object);
        }
    },

    addClassMethods: function(object) {
        cream.ensureClassRegistryLinked();
        if ( typeof(cream.classRegistry[object.class]) != 'undefined' ) {
            cream.addMethodsRecursivley(cream.classRegistry[object.class], object);
        }
        else {
            cream.addMethodsRecursivley(cream.classRegistry.Entity, object);
        }
    },

    addPropertiesAndRelationsRecursivley: function(creamClass, object) {
        if ( typeof(creamClass._extends) !== 'undefined' ) {
            cream.addPropertiesAndRelationsRecursivley(creamClass._extends, object);
            creamClass.addPropertiesAndRelations(object);
        }
        else {
            creamClass.addPropertiesAndRelations(object);
        }
    },

	
	/*******************
	*  Object structure
	********************/
	
    createEntity: function(saver, className, initData) {
        var object = {class: className, entityId: cream.getTemporaryId(className)};
        cream.idObjectMap[object.entityId] = object;

        cream.ensureClassRegistryLinked();
        cream.addPropertiesAndRelationsRecursivley(cream.classRegistry[className], object);
        cream.addClassMethods(object);

        saver.created(object);
        cream.activeSaver = saver;
        object.init(initData);
        cream.activeSaver = null;
        saver.initialized(object);

        cream.storeLoadValues(object);

        return object;
    },
		
	nextTemporaryId: 0,
    getTemporaryId: function(className) {
        return className + '.temporaryId=' + cream.nextTemporaryId;
    },
	
	propertyToSetterName : function(property) {
		return 'set' + capitalize(property);
	},
	
	propertyToGetterName : function(property) {
		return 'get' + capitalize(property);
	},
	
	waitingForIsLoadedOrException : false,
	gotUnloadedObject: null,
    initializeLoadedCreamObject: function(object) {
		if (typeof(object['load']) === 'undefined') {
			cream.initializeUnloadedCreamObject();
		}
		// if (typeof(object.initializedAlready) !== 'undefined') {
			// debugger;
		// }
		// object.initializedAlready = true;
		var createSetter = function(property) {
			var setterName = cream.propertyToSetterName(property);
			object[setterName] = function(value) {
				// console.log("try setting " + property);// debugger;
				var oldValue = object[property];
				// console.log("Inside setter for " + object.entityId + "." + property + " oldValue:" + oldValue + ", newValue:" + value);
				if (oldValue !== value) {
					console.log("Setting new value on " + property + " " + value + " old value: " + oldValue);
					object[property] = value;
					object.changed(property);
				}
			};				
		};
		
		var createGetter = function(property) {
			var getterName = cream.propertyToGetterName(property);
			object[getterName] = function() {
				
				// Throw error if we access unloaded object
				if (cream.repeatersBeeingRefreshed.length > 0) {
					// if (cream.waitingForIsLoadedOrException) {
						// cream.waitingForIsLoadedOrException = false;
						// currentRepeater.waitingForObjectToLoad = null;
					// }
					if (startsWithCapital(property)) {
						var currentRepeater = lastOfArray(cream.repeatersBeeingRefreshed);
						containsUnloaded = false;
						if (isArray(object[property])) {
							object[property].forEach(function(relatedObject) {
								if (!relatedObject.loaded) {
									currentRepeater.waitingForObjectToLoad = relatedObject;
									containsUnloaded = true;
								}
							});
						} else if (object[property] !== null && !object[property].loaded) {						
							currentRepeater.waitingForObjectToLoad = object[property];
							containsUnloaded = true;
						}
						
						if (containsUnloaded) {
							// throw new Error("Error: Accessing unloaded data");
							cream.waitingForIsLoadedOrException = true;
						}
					}
				}
				
				cream.readingPropertyOrRelation(object, property);
				return object[property];
			};
		};
		
		for(var property in object) {
			if (!inArray(property, ['entityId', 'phpEntityClass', 'loaded', 'class', 'load', 'isLoaded', 'loadUrgent', 'observers'])) {
				if (typeof(object[property]) !== 'function') {
					createSetter(property);
					createGetter(property);
				}					
			}
		}
		
		cream.addClassMethods(object);
	}, 
	
	initializeUnloadedCreamObject: function(object) {
        object.loadUrgent = function(extent, postLoadCallback) {
            cream.loadUrgent(object, extent, postLoadCallback);
        };

        object.load = function(extent, postLoadCallback) {
            cream.load(object, extent, postLoadCallback);
        };

		object.isLoaded = function() {
			if (arguments.length > 0) {
				var loadTag = arguments[0];
				if (!getter()) {
					return false;
				}
				return typeof(object.loadTags[loadTag]) !== 'undefined';
			} else {
				cream.waitingForIsLoadedOrException = false;
				cream.readingPropertyOrRelation(object, 'loaded');
				return object.loaded;
			}
		};
		
		object.observers = {};
	},
	
    initializeLastChanges: function(object, property) {
        if ( typeof(object.lastChanges) === 'undefined' ) {
            object.lastChanges = {};
        }
        if ( typeof(object.loadTags) === 'undefined' ) {
            object.loadTags = {};
        }
        var lastChanges = object.lastChanges;

        if ( inArray(property,
                ['loadValues', 'lastChanges', 'loaded', 'class', 'phpEntityClass', 'entityId']) ) {

            if ( typeof(lastChanges[property]) === 'undefined' ) {
                lastChanges[property] = null;

            }
        }
    },

	// Entity methods are also available on every object.
	EntityClassDefinition : {
		name: 'Entity',
		addPropertiesAndRelations: function(object) {
			// Note: This information needs to match what is in the database definition (fields and their default values) and the model and its relations.
		},
		addMethods: function(object) {
			object.touchedByEntity = true;

			object.init = function(initData) {
				for (var property in initData) {
					if ( typeof(object[property] !== 'undefined') ) {
						object[property] = initData;
						object.changed(property);
					}
				}
			};

			object.getSaver = function() {
				if (typeof(object.saver) !== 'undefined') {
					return object.saver;
				} else {
					return cream.defaultSaver;
				}
			};

			object.changed = function(fieldOrRelation) {
				var startedChangeSequence = false;
				if ( cream.activeSaver === null ) {
					cream.activeSaver = object.getSaver();
					if (cream.activeSaver === null) {
						console.log(object);
						throw("Could not find saver upon change!");
					}
					startedChangeSequence = true;
				}
				cream.activeSaver.changed(object, fieldOrRelation);
				// console.log("Changed " + object.entityId + '.' + fieldOrRelation);
				// console.log(object.observers[fieldOrRelation]);
				if (typeof(object.observers[fieldOrRelation]) !== 'undefined') { // New style of propagation
					// console.log('Repeater observers on this field or relation: ' + fieldOrRelation);
					var observers = object.observers[fieldOrRelation];
					for (var repeaterId in observers) {
						cream.repeaterDirty(observers[repeaterId]);
					}
				}
				

				if ( startedChangeSequence ) {
					cream.activeSaver = null;
				}
			};

			object.callOnServer = function(method, postCallCallback) { // TODO: an arglist
				cream.callOnServer(object, method, postCallCallback);
			};
		}
	},
	
	IndexClassDefinition : {
		name: 'Index',
		addPropertiesAndRelations: function(object) {
			// Note: This information needs to match what is in the database definition (fields and their default values) and the model and its relations.
		},
		addMethods: function(object) {
		}
	},
	

	/*********************
	*  Loading and saving
	**********************/
	
	traceLoading:false, // TODO: Remove when system stable, and easy tracing of this aspect not needed anymore.
    loadingQueue: [],
    defaultSaver: null,
    activeSaver: null,

    load: function(object, extent, postLoadCallback) {
		if(cream.traceLoading) console.log("Request load: " + object.entityId + ":" + extent);
        cream.loadingQueue.push({object: object, extent: extent, postLoadCallback: postLoadCallback, isBatchLoad:false});
		cream.checkLoadQueue();
    },

	batchLoad: function(requiredLoadings, postLoadCallback) {
		if(cream.traceLoading) console.log("Request batch load: " + object.entityId + ":" + extent);
        cream.loadingQueue.push({requiredLoadings: requiredLoadings, postLoadCallback: postLoadCallback, isBatchLoad:true});
		cream.checkLoadQueue();
	},

    loadUrgent: function(object, extent, postLoadCallback) {
		if(cream.traceLoading) console.log("Request load urgent: " + object.entityId + ":" + extent);
        cream.loadingQueue.unshift({object: object, extent: extent, postLoadCallback: postLoadCallback, isBatchLoad:false});
		cream.checkLoadQueue();
    },

    batchLoadUrgent: function(requiredLoadings, postLoadCallback) {
		if(cream.traceLoading) console.log("Request batch load urgent ");
        cream.loadingQueue.unshift({requiredLoadings: requiredLoadings, postLoadCallback: postLoadCallback, isBatchLoad:true});
		cream.checkLoadQueue();
	},

	loadingInProgress:false,
    checkLoadQueue: function() {
		if(cream.traceLoading) console.log("Check load queue: " + (cream.loadingInProgress ? "Loading in progress" : "Free to load") + ", Items in load queue: " + cream.loadingQueue.length);

        if ( !cream.loadingInProgress && cream.loadingQueue.length > 0 ) {
			cream.loadingInProgress = true;

            var loadRequest = cream.loadingQueue.shift();
			if (!loadRequest.isBatchLoad) {
				var postLoadCallback = function(loadedRelease) {
					cream.loadingInProgress = false;
					loadRequest.postLoadCallback(loadedRelease);
					cream.checkLoadQueue();
				};
				var errorCallback = function() {
					// console.log("in error callback");
					cream.loadingInProgress = false;
					cream.checkLoadQueue();
				};
				cream.performLoad(loadRequest.object, loadRequest.extent, postLoadCallback, errorCallback);
			} else {
				var postLoadCallback = function() {
					cream.loadingInProgress = false;
					loadRequest.postLoadCallback();
					cream.checkLoadQueue();
				};
				// TODO: use error callback on batch load also!
				cream.performBatchLoad(loadRequest.requiredLoadings,  postLoadCallback);
			}
        }
    },

    performLoad: function(object, extent, postLoadCallback, errorCallback) {
        if ( typeof(object.loadTags[extent]) != 'undefined' ) {
			if(cream.traceLoading) console.log("Already loaded " + object.entityId + ":" + extent);
            if ( typeof(postLoadCallback) != 'undefined' && postLoadCallback != null ) {
				if(cream.traceLoading) console.log("Post callback after already loaded");
                postLoadCallback(object);
            }
            return;
        }
        else {
            object.loadTags[extent] = true;
        }

		if(cream.traceLoading) console.log("Actually loading " + object.entityId + ":" + extent);
        $.ajax({
            data: null,
            dataType: 'html',
            success: function(text, textStatus) {
                try {
					// console.log("Retreiving data from server");
					// console.log(text);
                    data = JSON.parse(text);
                    globalAngularScope.$apply(function() {
						if(cream.traceLoading) console.log("Got back data for " + object.entityId + ":" + extent);
                        var wasLoadedNow = [];
						unserialize(data, wasLoadedNow);
						if ( typeof(postLoadCallback) != 'undefined' && postLoadCallback != null ) {
                            postLoadCallback(object);
                        }
						cream.holdingChangePropagation = true;
						wasLoadedNow.forEach(function(object) {
							if (typeof(object.observers['loaded']) !== 'undefined') { // New style of propagation
								// console.log('Repeater observers on this field or relation: ' + fieldOrRelation);
								var observers = object.observers['loaded'];
								for (var repeaterId in observers) {
									cream.repeaterDirty(observers[repeaterId]);
								}
							}									
						});
						cream.holdingChangePropagation = false;

						cream.notifyOnLoadRepeaters();
                    });

                }
                catch (error) {
                    if ( typeof(errorCallback) !== 'undefined' ) {
						if(cream.traceLoading) console.log("Error");
                        errorCallback();
                    }
                }
            },
            fail: function(text, textStatus) {
            },
            type: 'get',
            url: '/cream/load/' + object.entityId + '/' + extent
        });
    },

    performBatchLoad: function(requiredLoadings, postLoadCallback) {
        if ( requiredLoadings.length > 0 ) {
			// Check how many already are loaded, and how many still left
            var entityIdsAndExtents = {};
            var count = 0;
            requiredLoadings.forEach(function(loading) {
                if ( typeof(loading.object.loadTags[loading.extent]) === 'undefined' ) {
                    loading.object.loadTags[loading.extent] = true;
                    entityIdsAndExtents[loading.object.entityId] = loading.extent;
                    count++;
                }
            });

			if(cream.traceLoading) console.log("Actually batch loading: ");
			if(cream.traceLoading) console.log(entityIdsAndExtents);

            if ( count > 0 ) {
                $.ajax({
                    data: {entityIdsAndExtents: entityIdsAndExtents},
                    dataType: 'html',
                    success: function(text, textStatus) {
                        try {
                            data = JSON.parse(text);
							if(cream.traceLoading) console.log("Got back data for batch load");
                            globalAngularScope.$apply(function() {
								var wasLoadedNow = [];
                                data.forEach(function(loadedObject) {
									unserialize([loadedObject], wasLoadedNow);
                                });
                                if ( typeof(postLoadCallback) != 'undefined' && postLoadCallback != null ) {
                                    postLoadCallback();
                                }
								cream.holdingChangePropagation = true;
								wasLoadedNow.forEach(function(object) {
									if (typeof(object.observers['loaded']) !== 'undefined') { // New style of propagation
										// console.log('Repeater observers on this field or relation: ' + fieldOrRelation);
										var observers = object.observers['loaded'];
										for (var repeaterId in observers) {
											cream.repeaterDirty(observers[repeaterId]);
										}
									}									
								});
								cream.holdingChangePropagation = false;
								cream.notifyOnLoadRepeaters();
                            });

                        }
                        catch (error) {
                            console.log(arguments);
                            console.log('Error in cream load!');
                        }
                    },
                    fail: function(text, textStatus) {
                    },
                    type: 'post',
                    url: '/cream/batch_load/'
                });
            }
            else {
				if(cream.traceLoading) console.log("Already batch loaded everything in request. ");
                if ( typeof(postLoadCallback) != 'undefined' && postLoadCallback != null ) {
                    postLoadCallback();
                }
            }
        }
        else {
			if(cream.traceLoading) console.log("Nothing in batch load! ");
            if ( typeof(postLoadCallback) != 'undefined' && postLoadCallback != null ) {
                postLoadCallback();
            }
        }
    },

    applyServerChangeList: function(changelist) {
        changelist.forEach(function(change) {
            // TODO: Handle create and initialized.
            if ( typeof(cream.idObjectMap[change.entityId]) !== 'undefined' ) {
                var entity = getEntity(change.entityId);
                var relationOrProperty = change.relationOrProperty;
                var isRelation = relationOrProperty[0] === relationOrProperty[0].toUpperCase();

                if ( isRelation ) {
                    if ( angular.isArray(change.value) ) {
                        var relatedEntities = [];
                        change.value.forEach(function(entityId) {
                            relatedEntities.push(getEntity(entityId));
                        });
                        change.value = relatedEntities;
                    }
                    else {
                        change.value = getEntity(value);
                    }
                }

                entity[relationOrProperty] = change.value;
            }
        });
    },

	wasLoadedNow : [],
	wasCreatedNow : [],
    buildIdObjectMap: function(input, idObjectMap) {
        if ( input !== null ) {
            if ( angular.isArray(input) ) {
                input.forEach(function(element) {
                    cream.buildIdObjectMap(element, idObjectMap);
                });
            }
            else if ( typeof(input) === 'object' ) {
                // If already in idObjectMap, dont map it again, but continue recursivley
                if ( typeof(idObjectMap[input.entityId]) === 'undefined' ) {
                    // A new object of this entityId
					if(input.loaded) {
						cream.wasCreatedNow.push(input);
						cream.wasLoadedNow.push(input);
					} else {
						cream.wasCreatedNow.push(input);
					}
					
                    idObjectMap[input.entityId] = input;
                    // cream.initializeCreamObject(input); // Could some link not be initialized properly? If found out, move to a last stage.
                    // needsResolvance.push(input);
                }
                else {
                    // A previous object exists. Migrate primitive data to it.
                    var existingObject = idObjectMap[input.entityId];
                    if ( typeof(existingObject.lastChanges) == 'undefined' && typeof(input.lastChanges) != 'undefined' ) {
                        existingObject.lastChanges = input.lastChanges;
                        existingObject.created = input.created;
                    }

                    for (var tag in input.loadTags) {
                        existingObject.loadTags[tag] = true;
                    }

                    if ( !existingObject.loaded && input.loaded ) {
                        for (var property in input) {
                            if ( property[0].toUpperCase() !== property[0] ) {
                                if ( typeof(existingObject[property]) === 'undefined' ) {
                                    existingObject[property] = input[property];
                                }
                            }
                        }
                        existingObject.loaded = true;
                        // cream.initializeCreamObject(existingObject); // Could some link not be initialized properly? If found out, move to a last stage.
                        cream.wasLoadedNow.push(existingObject);
                    }
                }

                for (var property in input) {
                    if ( property[0].toUpperCase() === property[0] ) {
                        // Continue build objet map recursivley in other objects.
                        cream.buildIdObjectMap(input[property], idObjectMap);
                    }
                }
            }
            else {
                // It is just an entity idrep
            }
        }
    },

    replaceStringReferences: function(input, idObjectMap) {

        if ( input !== null ) {
            if ( typeof(input) === 'string' ) {
                return idObjectMap[input];
            }
            else if ( angular.isArray(input) ) {
                for (var i = 0; i < input.length; i++) {
                    input[i] = cream.replaceStringReferences(input[i], idObjectMap);
                }
                return input;
            }
            else if ( typeof(input) === 'object' ) {
                var replacement = idObjectMap[input.entityId]; // This could be entity or replacement

                if ( input.loaded ) {
                    for (var property in input) {
                        if ( property[0].toUpperCase() === property[0] ) {
                            // A relation
                            replacement[property] = cream.replaceStringReferences(input[property], idObjectMap);
                        }
                    }
                }
				
                // Store load values
                cream.storeLoadValues(replacement);

                return replacement;
            }
        }
        else {
            return null;
        }
    },

    storeLoadValues: function(object) {
        object.loadValues = {};
        for (var property in object) {
            cream.storeLoadValue(object, property);
        }
    },

    storeLoadValue: function(object, property) {
        if ( typeof(object[property]) !== 'function' ) {
            if ( inArray(property,
                    ['loadValues', 'lastChanges', 'loaded', 'class', 'phpEntityClass', 'entityId']) ) {
                // Store load value
                var value = object[property];
                var newValue = null;
                if ( angular.isArray(value) ) {
                    var newValue = [];
                    value.forEach(function(element) {
                        newValue.push(element);
                    });
                }
                else {
                    newValue = value;
                }
                object.loadValues[property] = newValue;
            }
        }
    }	
};
cream.initialize();

function repeatOnChange() {
	cream.repeatOnChange.apply(this, arguments);
}

function holdChangePropagation(callback) {
	cream.holdChangePropagation(callback);
}

function registerClass(creamClass) {
    cream.classRegistry[creamClass.name] = creamClass;
}

// An alternative to explicitly set the saver when doing an operation. Otherwise the object beeing manipulated first will define what saver is used.
function registerChange(saver, changeCallback) {
    if ( cream.activeSaver != null ) {
        alert('registerChange cannot be called while an ongoing change is already in progress');
    }
    cream.activeSaver = saver;
    changeCallback();
    cream.activeSaver = null;
}

function getEntity(entityId) {
    return cream.idObjectMap[entityId];
}

function createEntity(saver, creamClass, initData) {
    return cream.createEntity(saver, creamClass, initData);
}

function unserialize(input) { // If optionalSaver is undefined it will be used to set saver for all unserialized objects.
	var index = 0; 
	// Note: input is an array of objects to unserialize. However, the first object has special status and will be returned, the rest are loaded as a "bi-effect"
	while (index < input.length) {
		var object = input[index];
		var collectionOfLoaded = null;
		if (arguments.length > 1) {
			collectionOfLoaded = arguments[1];
		}

		cream.buildIdObjectMap(object, cream.idObjectMap);
		cream.replaceStringReferences(object, cream.idObjectMap);

		if (cream.wasCreatedNow.length > 0) {
			cream.wasCreatedNow.forEach(function(createdUnloaded) {
				cream.initializeUnloadedCreamObject(createdUnloaded);
			});
		}
		clearArray(cream.wasCreatedNow);

		if (cream.wasLoadedNow.length > 0) {
			cream.wasLoadedNow.forEach(function(newlyLoaded) {
				cream.initializeLoadedCreamObject(newlyLoaded); 
			});
		}
		if (collectionOfLoaded !== null) {
			pushArray(collectionOfLoaded, cream.wasLoadedNow);
		}
		clearArray(cream.wasLoadedNow);	
		input[index] = cream.idObjectMap[object.entityId]
		index++;
	}
	
	return input[0];
}


function createSaver(name) {
    var saver = {
        name: name,
        autosave: false,
        waitingToAutosave: false,
        allChangesSaved: true,
        savingChanges: false,
        changelist: [],
        postSaveEvents: [],

        changed: function(object, relationOrProperty) {

            if ( typeof(object.lastChanges) === 'undefined' ) {
                cream.initializeLastChanges(object, relationOrProperty);
            }

            object.lastChanges[relationOrProperty] = {
                'user': pilotUser,
                time: 'this session'
            };

            saver.allChangesSaved = false;
            var value = object[relationOrProperty];

            var isRelation = relationOrProperty[0] === relationOrProperty[0].toUpperCase();

            if ( isRelation ) {
                if (Array.isArray(value)) {
                    value = value.slice();
                }
            }

            var newEvent = {
                type: 'modify',
                'entity': object,
                'relationOrProperty': relationOrProperty,
                'value': value
            };

            saver.changelist = removeRedundantSessionChanges(saver.changelist, newEvent);
            saver.changelist.push(newEvent);

            saver.checkAutosave();
        },

        checkAutosave: function() {
            if ( saver.autosave ) {
                if ( !saver.waitingToAutosave ) {
                    setTimeout(function() {
                            saver.waitingToAutosave = false;
                            saver.save();
                        },
                        3000);
                }
                saver.waitingToAutosave = true;
            }
        },

        created: function(object) {
            var newEvent = {type: 'create', entity: object};
            saver.changelist.push(newEvent);
            saver.checkAutosave();
        },

        initialized: function(object) {
            var newEvent = {type: 'initialized', entity: object};
            saver.changelist.push(newEvent);
            saver.checkAutosave();
        },

        revert: function() {
            saver.changelist.forEach(function(change) {
                var entity = change.entity;
                entity[change.relationOrProperty] = entity.loadValues[change.relationOrProperty];
            });
            saver.changelist = [];
            saver.allChangesSaved = true;
        },

        updateLoadValues: function() {
            saver.changelist.forEach(function(change) {
                cream.storeLoadValue(change.entity, change.relationOrProperty);
            });
        },

        consolidateIds: function(temporaryEntityIdToEntityIdMap) {
            if ( !angular.isArray(temporaryEntityIdToEntityIdMap) ) {
                for (var tempId in temporaryEntityIdToEntityIdMap) {

                    var entityId = temporaryEntityIdToEntityIdMap[tempId];

                    // Replace in object self
                    var entity = getEntity(tempId);
                    entity.entityId = entityId;

                    // Replace in idObjectMap
                    cream.idObjectMap[entityId] = entity;
                    delete cream.idObjectMap.tempId;
                }
            }
        },

        serializeChangelist: function() {
            // Translate change from objects to entityIds. Do this here so that objects that change from a temporary id to a real one gets the right id in the save.
            saver.changelist.forEach(function(change) {
                var relationOrProperty = change.relationOrProperty;
                if ( typeof(relationOrProperty) !== 'undefined' ) {
                    var isRelation = relationOrProperty[0] === relationOrProperty[0].toUpperCase();

                    if ( isRelation ) {
                        if ( angular.isArray(change.value) ) {
                            var relatedIds = [];
                            change.value.forEach(function(relatedEntity) {
                                relatedIds.push(relatedEntity.entityId);
                            });
                            change.value = relatedIds;
                        }
                        else {
                            change.value = change.value.entityId;
                        }
                    }
                }
                change.entityId = change.entity.entityId;
                delete change.entity;
            });
        },

		traceSaving:true,
        save: function() {
			if (saver.traceSaving) {
				console.log("Saving data " + saver.name);
				console.log(saver.changelist);
			}
            saver.updateLoadValues();

            saver.serializeChangelist();

            $.ajax({
                data: {changelist: saver.changelist},
                dataType: 'html',
                success: function(text, textStatus) {
                    try {
                        temporaryEntityIdToEntityIdMap = JSON.parse(text);
                        saver.consolidateIds(temporaryEntityIdToEntityIdMap);

                        globalAngularScope.$apply(function() {
                            saver.savingChanges = false;
                            if ( saver.changelist.length === 0 ) {
                                saver.allChangesSaved = true;
                            }
                            saver.postSaveEvents.forEach(function(event) {
                                try {
                                    event();
                                }
                                catch (error) {
                                    console.log('Error in post save event');
                                    console.log(error);
                                }
                            });
                        });

                    }
                    catch (error) {
						console.log("Error on receiving save data");
						console.log(text);
                        console.log(error);
                    }
                },
                fail: function(text, textStatus) {
                },
                type: 'post',
                url: '/cream/save'
            });
            saver.savingChanges = true;
            saver.changelist = [];
        },

        saveChangelist: function(changelist) {
            $.ajax({
                data: {changelist: changelist},
                dataType: 'html',
                success: function(text, textStatus) {
                },
                fail: function(text, textStatus) {
                },
                type: 'post',
                url: '/cream/save'
            });
            changelist = [];
        }

    };
    return saver;
}

function removeRedundantSessionChanges(changelist, event) {
    var newList = [];
    changelist.forEach(function(loggedEvent) {
        if ( loggedEvent.entity == event.entity && loggedEvent.relationOrProperty == event.relationOrProperty ) {
            // Skip this event
        }
        else {
            newList.push(loggedEvent);
        }
    });
    return newList;
}

