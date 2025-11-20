export type EmploymentRow = {
  stateCode: string;
  stateName: string;
  male?: number | null;
  female?: number | null;
  total?: number | null;
};

export type EmploymentError = {
  stateCode?: string;
  sex?: string;
  message?: string;
};
