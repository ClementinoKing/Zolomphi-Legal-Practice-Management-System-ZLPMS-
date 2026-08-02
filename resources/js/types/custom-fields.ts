export type CustomFieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'date'
    | 'select'
    | 'boolean';

export type CustomFieldDefinition = {
    id: string;
    key: string;
    label: string;
    type: CustomFieldType;
    placeholder: string;
    options: string[];
    required: boolean;
    applies_to: Array<'individual' | 'organization'>;
    step: 1 | 2 | 3;
    sort_order: number;
};
