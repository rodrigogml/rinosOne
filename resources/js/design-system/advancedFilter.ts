export type AdvancedFilterOperator = 'EQUALS' | 'NOT_EQUALS' | 'CONTAINS' | 'NOT_CONTAINS' | 'STARTS_WITH' | 'ENDS_WITH' | 'IS_EMPTY' | 'IS_NOT_EMPTY' | 'IN' | 'NOT_IN';
export type AdvancedFilterCombinator = 'AND' | 'OR';
export type AdvancedFilterMatchMode = 'ANY_RECORD' | 'SAME_RECORD';

export interface AdvancedFilterOption { value: string; label: string; }
export interface AdvancedFilterField { key: string; label: string; type: 'TEXT' | 'ENUM'; relation: string | null; operators: AdvancedFilterOperator[]; options: AdvancedFilterOption[]; }
export interface AdvancedFilterRelation { key: string; label: string; }
export interface AdvancedFilterSchema { maximumDepth: number; maximumConditions: number; relations: AdvancedFilterRelation[]; fields: AdvancedFilterField[]; }
export interface AdvancedFilterCondition { kind: 'condition'; id: string; field: string; operator: AdvancedFilterOperator; value?: string | string[]; negated: boolean; }
export interface AdvancedFilterGroup { kind: 'group'; id: string; combinator: AdvancedFilterCombinator; negated: boolean; matchMode: AdvancedFilterMatchMode; relation: string | null; children: AdvancedFilterNode[]; }
export type AdvancedFilterNode = AdvancedFilterCondition | AdvancedFilterGroup;

function nodeId(): string { return globalThis.crypto?.randomUUID?.() ?? `filter-${Date.now()}-${Math.random().toString(36).slice(2)}`; }
export function createAdvancedFilterGroup(): AdvancedFilterGroup { return { kind: 'group', id: nodeId(), combinator: 'AND', negated: false, matchMode: 'ANY_RECORD', relation: null, children: [] }; }
export function createAdvancedFilterNodeId(): string { return nodeId(); }
