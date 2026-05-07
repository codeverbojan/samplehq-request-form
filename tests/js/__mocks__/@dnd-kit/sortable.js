function arrayMove( array, from, to ) {
	const newArray = [ ...array ];
	const [ item ] = newArray.splice( from, 1 );
	newArray.splice( to, 0, item );
	return newArray;
}

module.exports = {
	arrayMove,
	SortableContext: ( { children } ) => children,
	verticalListSortingStrategy: jest.fn(),
	useSortable: jest.fn( () => ( {
		attributes: {},
		listeners: {},
		setNodeRef: jest.fn(),
		transform: null,
		transition: null,
		isDragging: false,
		isOver: false,
	} ) ),
	sortableKeyboardCoordinates: jest.fn(),
};
