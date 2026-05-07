import { __ } from '@wordpress/i18n';
import {
	Type,
	AtSign,
	Phone,
	AlignLeft,
	Hash,
	User,
	ChevronDown,
	CircleDot,
	CheckSquare,
	Calendar,
	Link,
	Upload,
	MapPin,
	EyeOff,
	Code,
	ShieldCheck,
	LayoutGrid,
	Columns2,
} from 'lucide-react';

export const FIELD_CATEGORIES = [
	{
		label: __( 'Standard', 'samplehq-request-form' ),
		fields: [
			{
				type: 'text',
				label: __( 'Text', 'samplehq-request-form' ),
				icon: Type,
			},
			{
				type: 'email',
				label: __( 'Email', 'samplehq-request-form' ),
				icon: AtSign,
			},
			{
				type: 'phone',
				label: __( 'Phone', 'samplehq-request-form' ),
				icon: Phone,
			},
			{
				type: 'textarea',
				label: __( 'Paragraph', 'samplehq-request-form' ),
				icon: AlignLeft,
			},
			{
				type: 'number',
				label: __( 'Number', 'samplehq-request-form' ),
				icon: Hash,
			},
			{
				type: 'name',
				label: __( 'Name', 'samplehq-request-form' ),
				icon: User,
			},
		],
	},
	{
		label: __( 'Choice', 'samplehq-request-form' ),
		fields: [
			{
				type: 'select',
				label: __( 'Dropdown', 'samplehq-request-form' ),
				icon: ChevronDown,
			},
			{
				type: 'radio',
				label: __( 'Radio', 'samplehq-request-form' ),
				icon: CircleDot,
			},
			{
				type: 'checkbox',
				label: __( 'Checkbox', 'samplehq-request-form' ),
				icon: CheckSquare,
			},
		],
	},
	{
		label: __( 'Advanced', 'samplehq-request-form' ),
		fields: [
			{
				type: 'date',
				label: __( 'Date', 'samplehq-request-form' ),
				icon: Calendar,
			},
			{
				type: 'url',
				label: __( 'URL', 'samplehq-request-form' ),
				icon: Link,
			},
			{
				type: 'file_upload',
				label: __( 'File Upload', 'samplehq-request-form' ),
				icon: Upload,
			},
			{
				type: 'address',
				label: __( 'Address', 'samplehq-request-form' ),
				icon: MapPin,
			},
			{
				type: 'hidden',
				label: __( 'Hidden', 'samplehq-request-form' ),
				icon: EyeOff,
			},
		],
	},
	{
		label: __( 'Content', 'samplehq-request-form' ),
		fields: [
			{
				type: 'html',
				label: __( 'HTML', 'samplehq-request-form' ),
				icon: Code,
			},
			{
				type: 'consent',
				label: __( 'Consent', 'samplehq-request-form' ),
				icon: ShieldCheck,
			},
			{
				type: 'sample_picker',
				label: __( 'Sample Picker', 'samplehq-request-form' ),
				icon: LayoutGrid,
			},
		],
	},
	{
		label: __( 'Layout', 'samplehq-request-form' ),
		fields: [
			{
				type: 'row',
				label: __( 'Row / Columns', 'samplehq-request-form' ),
				icon: Columns2,
			},
		],
	},
];

export const ALL_FIELD_TYPES = FIELD_CATEGORIES.flatMap( ( c ) => c.fields );

export const CONDITION_OPERATORS = [
	{ value: 'equals', label: __( 'equals', 'samplehq-request-form' ) },
	{
		value: 'not_equals',
		label: __( 'does not equal', 'samplehq-request-form' ),
	},
	{ value: 'contains', label: __( 'contains', 'samplehq-request-form' ) },
	{
		value: 'not_contains',
		label: __( 'does not contain', 'samplehq-request-form' ),
	},
	{ value: 'empty', label: __( 'is empty', 'samplehq-request-form' ) },
	{
		value: 'not_empty',
		label: __( 'is not empty', 'samplehq-request-form' ),
	},
];

export const HISTORY_LIMIT = 30;
