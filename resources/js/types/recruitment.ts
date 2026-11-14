export type ApplicationTeamMember = {
    id: number;
    name: string;
} | null;

export type ApplicationNoteItem = {
    id: number;
    body: string;
    edited_at: string | null;
    created_at: string | null;
    author: ApplicationTeamMember;
    can_edit: boolean;
    can_delete: boolean;
};

export type EvaluationCriterionRatings = {
    relevant_experience: number;
    technical_skills: number;
    communication: number;
    interview_performance: number;
};

export type ApplicationEvaluationItem = {
    id: number;
    created_at: string | null;
    evaluator: ApplicationTeamMember;
    ratings: EvaluationCriterionRatings;
    comment: string | null;
};
