module.exports = {
	DndContext: ( { children } ) => children,
	DragOverlay: ( { children } ) => children || null,
	closestCorners: jest.fn(),
	PointerSensor: jest.fn(),
	KeyboardSensor: jest.fn(),
	useSensor: jest.fn( () => ( {} ) ),
	useSensors: jest.fn( () => [] ),
	useDroppable: jest.fn( () => ( {
		setNodeRef: jest.fn(),
		isOver: false,
	} ) ),
	useDraggable: jest.fn( () => ( {
		attributes: {},
		listeners: {},
		setNodeRef: jest.fn(),
		isDragging: false,
	} ) ),
};
