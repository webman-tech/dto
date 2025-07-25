<?php

namespace WebmanTech\DTO;

use DateTime;
use DateTimeInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use WebmanTech\DTO\Attributes\ToArrayConfig;
use WebmanTech\DTO\Exceptions\DTONewInstanceException;
use WebmanTech\DTO\Exceptions\DTOValidateException;
use WebmanTech\DTO\Helper\ConfigHelper;
use WebmanTech\DTO\Reflection\ReflectionReaderFactory;

class BaseDTO
{
    /**
     * 根据 data 构建实例
     * @throws DTONewInstanceException|DTOValidateException
     */
    public static function fromData(array $data, bool $validate = true): static
    {
        $factory = ReflectionReaderFactory::fromClass(static::class);

        if ($validate) {
            // 验证必须的规则
            $data = static::validateData($data, $factory->getPropertiesValidationRules());
            // 验证自定义的规则
            $data = static::validateData($data, static::getExtraValidationRules());
        }

        try {
            return $factory->newInstanceByData($data);
        } catch (\Throwable $e) {
            throw new DTONewInstanceException(static::class, $e);
        }
    }

    /**
     * 获取额外的验证规则
     * @return array
     */
    protected static function getExtraValidationRules(): array
    {
        return [];
    }

    /**
     * 验证数据
     * @param array<string, mixed> $data
     * @return array<string, mixed> 验证过的数据
     * @throws DTOValidateException
     */
    protected static function validateData(array $data, array $rules): array
    {
        if (!$rules) {
            return $data;
        }
        /** @var Validator $validator */
        $validator = validator($data, $rules);
        if ($validator->fails()) {
            throw new DTOValidateException(
            // 只取每个 key 的第一次个错误
                array_map(
                    fn($messages) => is_array($messages) ? Arr::first($messages) : $messages,
                    $validator->errors()->toArray(),
                )
            );
        }

        return $data;
    }

    /**
     * 日期格式
     */
    protected function getDateTimeFormat(): string
    {
        return ConfigHelper::get('dto.to_array_default_datetime_format', DateTimeInterface::ATOM);
    }

    /**
     * 转为数组
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        $factory = ReflectionReaderFactory::fromClass($this);

        $toArrayConfig = $factory->getPropertiesToArrayConfig() ?? new ToArrayConfig();
        if ($toArrayConfig->only) {
            $properties = $toArrayConfig->only;
        } else {
            $properties = $factory->getPropertyNameList();
            if ($toArrayConfig->include) {
                $properties = array_merge($properties, $toArrayConfig->include);
            }
            if ($toArrayConfig->exclude) {
                $properties = array_diff($properties, $toArrayConfig->exclude);
            }
        }

        foreach ($properties as $property) {
            $value = $this->{$property};
            if (is_array($value)) {
                $value = array_map(function ($item) {
                    if ($item instanceof self) {
                        return $item->toArray();
                    }
                    return $item;
                }, $value);
            } elseif ($value instanceof self) {
                $value = $value->toArray();
            } elseif ($value instanceof DateTime) {
                $value = $value->format($this->getDateTimeFormat());
            }
            $data[$property] = $value;
        }

        return $data;
    }
}
