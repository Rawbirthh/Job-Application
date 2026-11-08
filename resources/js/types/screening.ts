export type ScreeningQuestionType =
    | 'short_text'
    | 'long_text'
    | 'yes_no'
    | 'single_choice';

export type BankScreeningQuestion = {
    id: number;
    type: ScreeningQuestionType;
    type_label: string;
    label: string;
    options: string[] | null;
    is_required: boolean;
    is_active: boolean;
    templates_count?: number;
    jobs_count?: number;
    sort_order?: number;
};

export type ScreeningTemplateQuestion = BankScreeningQuestion & {
    sort_order: number;
};

export type ScreeningTemplate = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    questions_count?: number;
    question_ids?: number[];
    questions?: ScreeningTemplateQuestion[];
};

export type JobScreeningQuestion = {
    id: number;
    type: ScreeningQuestionType;
    type_label: string;
    label: string;
    options: string[] | null;
    is_required: boolean;
    sort_order: number;
    is_active: boolean;
    answers_count?: number;
};

export type ScreeningAnswer = {
    question_id: number;
    question_label: string | null;
    question_type: ScreeningQuestionType | null;
    options: string[] | null;
    answer_text: string | null;
    answer_boolean: boolean | null;
    selected_option: string | null;
};

export type ScreeningQuestionFormData = {
    type: ScreeningQuestionType;
    label: string;
    options: string[];
    is_required: boolean;
    is_active: boolean;
};

export type ScreeningQuestionForApply = {
    id: number;
    type: ScreeningQuestionType;
    label: string;
    options: string[] | null;
    is_required: boolean;
};

export type ScreeningQuestionsIndexProps = {
    questions: {
        data: BankScreeningQuestion[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export type ScreeningTemplatesIndexProps = {
    templates: {
        data: ScreeningTemplate[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    bank_questions: BankScreeningQuestion[];
};

export type JobScreeningQuestionsIndexProps = {
    job: { id: number; title: string; status: string };
    questions: JobScreeningQuestion[];
    templates: { id: number; name: string; questions_count: number }[];
    bank_questions: BankScreeningQuestion[];
};
