<?php

function normalizeStream($streamRaw) {
    $stream = strtolower(trim((string)$streamRaw));
    if ($stream === 'natural' || $stream === 'social') {
        return $stream;
    }
    return '';
}

function validateStreamForGrade($gradeDigits, $streamRaw) {
    $g = (int)$gradeDigits;
    $stream = normalizeStream($streamRaw);
    if ($g >= 11 && $g <= 12) {
        return $stream !== '' ? [true, $stream] : [false, 'For Grade 11 and 12, stream is required (natural/social).'];
    }
    return [true, ''];
}

function curriculumSubjects($gradeDigits, $streamRaw = '') {
    $g = (int)$gradeDigits;
    $stream = normalizeStream($streamRaw);

    if ($g === 9 || $g === 10) {
        return [
            'Mathematics',
            'English',
            'Civic',
            'Physics',
            'Chemistry',
            'Biology',
            'Geography',
            'History',
            'IT',
            'Sports'
        ];
    }

    if (($g === 11 || $g === 12) && $stream === 'natural') {
        return [
            'Mathematics',
            'English',
            'Physics',
            'Chemistry',
            'Biology',
            'Introduction to AI',
            'Drawing',
            'Introduction to Engineering'
        ];
    }

    if (($g === 11 || $g === 12) && $stream === 'social') {
        return [
            'Mathematics',
            'English',
            'Geography',
            'History',
            'Economics',
            'Agriculture'
        ];
    }

    return [];
}

function curriculumSubjectCount($gradeDigits, $streamRaw = '') {
    return count(curriculumSubjects($gradeDigits, $streamRaw));
}

?>
