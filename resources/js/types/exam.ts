export interface ExamQuestionOption {
  a?: string
  b?: string
  c?: string
  d?: string
}

export interface ExamQuestion {
  id: number
  question_text: string
  subject?: string | null
  subject_name?: string | null
  subject_label?: string | null
  option_a?: string | null
  option_b?: string | null
  option_c?: string | null
  option_d?: string | null
  options?: ExamQuestionOption
}

export interface ExamCurrent {
  examId: number
  attemptId: number
  questions: ExamQuestion[]
  duration?: number
  hasNegativeMarking?: boolean
  title?: string
  perPage?: number
  isRetryWrong?: boolean
}

export interface ExamStartPayload {
  attempt_id?: number
  attempt?: { id: number }
  questions?: ExamQuestion[]
  answers?: Record<string | number, string>
  end_time?: number
  per_page?: number
  duration_minutes?: number
}

export interface AutosavePayload {
  answers: Record<string | number, string>
}

export interface AutosaveResponse {
  attempt_id?: number
  saved_at?: string
  answers_count?: number
  answers?: Record<string | number, string>
  status?: string
}

export interface ExamAttemptCache {
  current: ExamCurrent | null
  answers: Record<string | number, string>
  endsAt: number | string | null
  dirty: boolean
  lastSyncedAt: string | null
  pageIndex: number
  flagged: number[]
}
